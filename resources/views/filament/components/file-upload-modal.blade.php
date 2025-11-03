@props([
    'serverId',
    'currentPath',
    'uploadSizeLimit',
])

<div 
    x-data="{
        files: [],
        isDragging: false,
        isUploading: false,
        uploadProgress: [],
        
        init() {
            // Nothing to initialize on mount
        },
        
        handleDragEnter(e) {
            e.preventDefault();
            e.stopPropagation();
            this.isDragging = true;
        },
        
        handleDragOver(e) {
            e.preventDefault();
            e.stopPropagation();
        },
        
        handleDragLeave(e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.target === this.\$refs.dropzone) {
                this.isDragging = false;
            }
        },
        
        handleDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            this.isDragging = false;
            
            const files = Array.from(e.dataTransfer.files);
            this.addFiles(files);
        },
        
        handleFileSelect(e) {
            const files = Array.from(e.target.files);
            this.addFiles(files);
            e.target.value = ''; // Reset input
        },
        
        addFiles(newFiles) {
            const uploadSizeLimit = {{ $uploadSizeLimit }};
            
            newFiles.forEach(file => {
                if (file.size > uploadSizeLimit) {
                    const limitMB = (uploadSizeLimit / 1024 / 1024).toFixed(2);
                    window.FilamentNotification()
                        .title('{{ trans('server/file.actions.upload.file_too_large') }}')
                        .body(`\${file.name} {{ trans('server/file.actions.upload.exceeds_limit') }} \${limitMB} MB`)
                        .danger()
                        .send();
                    return;
                }
                
                this.files.push({
                    file: file,
                    name: file.name,
                    size: file.size,
                    status: 'pending',
                    progress: 0,
                    speed: 0,
                    error: null
                });
            });
            
            if (this.files.length > 0 && !this.isUploading) {
                this.startUpload();
            }
        },
        
        async startUpload() {
            this.isUploading = true;
            const maxConcurrent = 3;
            const queue = [...this.files.filter(f => f.status === 'pending')];
            const active = [];
            
            while (queue.length > 0 || active.length > 0) {
                // Start new uploads up to the concurrency limit
                while (active.length < maxConcurrent && queue.length > 0) {
                    const fileItem = queue.shift();
                    const promise = this.uploadFile(fileItem);
                    active.push(promise);
                }
                
                // Wait for at least one upload to complete
                if (active.length > 0) {
                    await Promise.race(active);
                    // Remove completed promises
                    for (let i = active.length - 1; i >= 0; i--) {
                        if (active[i].isSettled) {
                            active.splice(i, 1);
                        }
                    }
                }
            }
            
            // All uploads complete
            const completed = this.files.filter(f => f.status === 'complete').length;
            const failed = this.files.filter(f => f.status === 'failed').length;
            
            if (failed === 0) {
                window.FilamentNotification()
                    .title('{{ trans('server/file.actions.upload.success_title') }}')
                    .body(`{{ trans('server/file.actions.upload.success_body') }}`)
                    .success()
                    .send();
            } else if (completed > 0) {
                window.FilamentNotification()
                    .title('{{ trans('server/file.actions.upload.partial_title') }}')
                    .body(`\${completed} {{ trans('server/file.actions.upload.partial_body') }} \${failed} {{ trans('server/file.actions.upload.failed') }}`)
                    .warning()
                    .send();
            } else {
                window.FilamentNotification()
                    .title('{{ trans('server/file.actions.upload.all_failed_title') }}')
                    .body('{{ trans('server/file.actions.upload.all_failed_body') }}')
                    .danger()
                    .send();
            }
            
            // Auto-close after 5 seconds
            setTimeout(() => {
                this.isUploading = false;
                this.files = [];
                this.\$wire.call('uploadComplete');
            }, 5000);
        },
        
        async uploadFile(fileItem) {
            fileItem.status = 'uploading';
            
            try {
                // Get upload URL from Livewire
                const uploadUrl = await this.\$wire.getUploadUrl();
                
                return new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    const formData = new FormData();
                    formData.append('files', fileItem.file);
                    
                    let startTime = Date.now();
                    let lastLoaded = 0;
                    
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            fileItem.progress = Math.round((e.loaded / e.total) * 100);
                            
                            const currentTime = Date.now();
                            const timeElapsed = (currentTime - startTime) / 1000;
                            const bytesUploaded = e.loaded - lastLoaded;
                            
                            if (timeElapsed > 0) {
                                fileItem.speed = bytesUploaded / timeElapsed;
                                startTime = currentTime;
                                lastLoaded = e.loaded;
                            }
                        }
                    });
                    
                    xhr.addEventListener('load', () => {
                        if (xhr.status === 200) {
                            fileItem.status = 'complete';
                            fileItem.progress = 100;
                            resolve({ isSettled: true });
                        } else {
                            fileItem.status = 'failed';
                            fileItem.error = xhr.statusText || 'Upload failed';
                            resolve({ isSettled: true });
                        }
                    });
                    
                    xhr.addEventListener('error', () => {
                        fileItem.status = 'failed';
                        fileItem.error = 'Network error';
                        resolve({ isSettled: true });
                    });
                    
                    xhr.addEventListener('abort', () => {
                        fileItem.status = 'failed';
                        fileItem.error = 'Upload cancelled';
                        resolve({ isSettled: true });
                    });
                    
                    xhr.open('POST', uploadUrl);
                    xhr.send(formData);
                });
            } catch (error) {
                fileItem.status = 'failed';
                fileItem.error = error.message || 'Failed to get upload URL';
                return { isSettled: true };
            }
        },
        
        formatBytes(bytes) {
            if (bytes === 0) return '0.00 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        handleEscapeKey(e) {
            if (e.key === 'Escape' && this.isUploading) {
                this.isUploading = false;
                this.files = [];
            }
        }
    }"
    @keydown.window="handleEscapeKey"
    class="w-full"
>
    <!-- Drop Zone -->
    <div
        x-ref="dropzone"
        @dragenter="handleDragEnter"
        @dragover="handleDragOver"
        @dragleave="handleDragLeave"
        @drop="handleDrop"
        :class="{
            'border-primary-500 bg-primary-50 dark:bg-primary-950': isDragging,
            'border-gray-300 dark:border-gray-600': !isDragging
        }"
        class="relative border-2 border-dashed rounded-lg p-8 text-center transition-colors duration-200"
    >
        <input
            type="file"
            @change="handleFileSelect"
            multiple
            class="hidden"
            x-ref="fileInput"
        />
        
        <div class="space-y-4">
            <div class="flex justify-center">
                <svg class="size-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
            </div>
            
            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ trans('server/file.actions.upload.drag_drop_hint') }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ trans('server/file.actions.upload.or') }}
                </p>
            </div>
            
            <div>
                <button
                    type="button"
                    @click="$refs.fileInput.click()"
                    class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors duration-200"
                >
                    <svg class="size-4 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ trans('server/file.actions.upload.browse_files') }}
                </button>
            </div>
            
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ trans('server/file.actions.upload.max_size') }}: {{ number_format($uploadSizeLimit / 1024 / 1024, 2) }} MB
            </p>
        </div>
    </div>
    
    <!-- Upload Progress Dialog -->
    <div
        x-show="isUploading"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        style="display: none;"
    >
        <div class="w-1/2 max-h-[50vh] bg-white dark:bg-gray-800 rounded-lg shadow-xl flex flex-col">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-center">
                    {{ trans('server/file.actions.upload.uploading_files') }}
                    <span x-text="`(\${files.filter(f => f.status === 'complete').length} {{ trans('server/file.actions.upload.of') }} \${files.length} {{ trans('server/file.actions.upload.completed') }})`"></span>
                </h3>
            </div>
            
            <!-- Table -->
            <div class="flex-1 overflow-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                        <tr>
                            <th class="px-4 py-4 sm:px-6 text-start text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ trans('server/file.actions.upload.file_name') }}
                            </th>
                            <th class="px-4 py-4 sm:px-6 text-start text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ trans('server/file.actions.upload.size') }}
                            </th>
                            <th class="px-4 py-4 sm:px-6 text-start text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ trans('server/file.actions.upload.progress') }}
                            </th>
                            <th class="px-4 py-4 sm:px-6 text-start text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ trans('server/file.actions.upload.status') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        <template x-for="(fileItem, index) in files" :key="index">
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150">
                                <td class="px-4 py-4 sm:px-6 text-sm text-gray-900 dark:text-gray-100">
                                    <div>
                                        <div x-text="fileItem.name" class="font-medium"></div>
                                        <div x-show="fileItem.error" class="text-xs text-red-600 dark:text-red-400 mt-1" x-text="fileItem.error"></div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 sm:px-6 text-sm text-gray-700 dark:text-gray-300">
                                    <span x-text="formatBytes(fileItem.size)"></span>
                                </td>
                                <td class="px-4 py-4 sm:px-6 text-sm text-gray-700 dark:text-gray-300">
                                    <div class="flex justify-between items-center">
                                        <span x-text="`\${fileItem.progress}%`"></span>
                                        <span x-show="fileItem.status === 'uploading' && fileItem.speed > 0" class="text-end" x-text="formatBytes(fileItem.speed) + '/s'"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 sm:px-6">
                                    <div class="flex items-center gap-2">
                                        <!-- Pending -->
                                        <span x-show="fileItem.status === 'pending'" class="flex items-center gap-2">
                                            <span class="size-2 rounded-full bg-gray-400"></span>
                                        </span>
                                        <!-- Uploading -->
                                        <span x-show="fileItem.status === 'uploading'" class="flex items-center gap-2">
                                            <span class="size-2 rounded-full bg-primary-600 animate-pulse"></span>
                                        </span>
                                        <!-- Complete -->
                                        <span x-show="fileItem.status === 'complete'" class="flex items-center gap-2">
                                            <span class="size-2 rounded-full bg-success-500"></span>
                                        </span>
                                        <!-- Failed -->
                                        <span x-show="fileItem.status === 'failed'" class="flex items-center gap-2">
                                            <span class="size-2 rounded-full bg-danger-500"></span>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
