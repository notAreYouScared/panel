<x-filament-panels::page>
    <div
        x-data="{
            isDragging: false,
            dragCounter: 0,
            isUploading: false,
            uploadQueue: [],
            currentFileIndex: 0,
            totalFiles: 0,
            handleDragEnter(e) {
                e.preventDefault();
                e.stopPropagation();
                this.dragCounter++;
                this.isDragging = true;
            },
            handleDragLeave(e) {
                e.preventDefault();
                e.stopPropagation();
                this.dragCounter--;
                if (this.dragCounter === 0) {
                    this.isDragging = false;
                }
            },
            handleDragOver(e) {
                e.preventDefault();
                e.stopPropagation();
            },
            async handleDrop(e) {
                e.preventDefault();
                e.stopPropagation();
                this.isDragging = false;
                this.dragCounter = 0;

                const files = e.dataTransfer.files;
                if (files.length === 0) return;

                await this.uploadFiles(files);
            },
            async uploadFiles(files) {
                this.isUploading = true;
                this.uploadQueue = [];
                this.totalFiles = files.length;
                this.currentFileIndex = 1;

                // Initialize queue with file metadata
                for (let i = 0; i < files.length; i++) {
                    this.uploadQueue.push({
                        file: files[i],
                        name: files[i].name,
                        size: files[i].size,
                        progress: 0,
                        speed: 0,
                        uploadedBytes: 0,
                        totalBytes: files[i].size,
                        status: 'uploading', // all files start as uploading in bulk mode
                        error: null
                    });
                }

                try {
                    // Get upload URL from Livewire (once for all files)
                    const uploadUrl = await $wire.getUploadUrl();
                    const url = new URL(uploadUrl);
                    url.searchParams.append('directory', @js($this->path));

                    // Upload all files in a single request (bulk upload)
                    const formData = new FormData();
                    let totalSize = 0;
                    for (let i = 0; i < files.length; i++) {
                        formData.append('files', files[i]);
                        totalSize += files[i].size;
                    }

                    await new Promise((resolve, reject) => {
                        const xhr = new XMLHttpRequest();
                        let lastLoaded = 0;
                        let lastTime = Date.now();

                        xhr.upload.addEventListener('progress', (e) => {
                            if (e.lengthComputable) {
                                const overallProgress = Math.round((e.loaded / e.total) * 100);
                                
                                // Calculate upload speed
                                const currentTime = Date.now();
                                const timeDiff = (currentTime - lastTime) / 1000;
                                let currentSpeed = 0;
                                if (timeDiff > 0.1) {
                                    const bytesDiff = e.loaded - lastLoaded;
                                    currentSpeed = bytesDiff / timeDiff;
                                    lastTime = currentTime;
                                    lastLoaded = e.loaded;
                                }

                                // Update all files proportionally
                                for (let i = 0; i < this.uploadQueue.length; i++) {
                                    const fileData = this.uploadQueue[i];
                                    const fileProportion = fileData.totalBytes / totalSize;
                                    fileData.uploadedBytes = Math.round(e.loaded * fileProportion);
                                    fileData.progress = overallProgress;
                                    fileData.speed = currentSpeed * fileProportion;
                                }
                            }
                        });

                        xhr.addEventListener('load', () => {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                // Mark all files as complete
                                for (let i = 0; i < this.uploadQueue.length; i++) {
                                    this.uploadQueue[i].status = 'complete';
                                    this.uploadQueue[i].progress = 100;
                                }
                                resolve();
                            } else {
                                // Mark all files as failed
                                for (let i = 0; i < this.uploadQueue.length; i++) {
                                    this.uploadQueue[i].status = 'error';
                                    this.uploadQueue[i].error = `Upload failed (${xhr.status})`;
                                }
                                reject(new Error(`Upload failed with status: ${xhr.status}`));
                            }
                        });

                        xhr.addEventListener('error', () => {
                            // Mark all files as failed
                            for (let i = 0; i < this.uploadQueue.length; i++) {
                                this.uploadQueue[i].status = 'error';
                                this.uploadQueue[i].error = 'Network error';
                            }
                            reject(new Error('Upload failed'));
                        });

                        xhr.addEventListener('abort', () => {
                            // Mark all files as failed
                            for (let i = 0; i < this.uploadQueue.length; i++) {
                                this.uploadQueue[i].status = 'error';
                                this.uploadQueue[i].error = 'Upload cancelled';
                            }
                            reject(new Error('Upload aborted'));
                        });

                        xhr.open('POST', url.toString());
                        xhr.send(formData);
                    });

                    // Refresh the component to show new files
                    await $wire.$refresh();

                    // Show success notification
                    new window.FilamentNotification()
                        .title('{{ trans('server/file.actions.upload.success') }}')
                        .success()
                        .send();

                } catch (error) {
                    console.error('Upload failed:', error);
                    
                    // Show error notification
                    new window.FilamentNotification()
                        .title('{{ trans('server/file.actions.upload.failed') }}')
                        .danger()
                        .send();
                } finally {
                    // Keep the dialog open for 2 seconds to show completion
                    setTimeout(() => {
                        this.isUploading = false;
                        this.uploadQueue = [];
                    }, 2000);
                }
            },
            formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
            },
            formatSpeed(bytesPerSecond) {
                return this.formatBytes(bytesPerSecond) + '/s';
            }
        }"
        @dragenter.window="handleDragEnter($event)"
        @dragleave.window="handleDragLeave($event)"
        @dragover.window="handleDragOver($event)"
        @drop.window="handleDrop($event)"
        class="relative"
    >
        <!-- Drag & Drop Overlay -->
        <div
            x-show="isDragging"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-gray-100/20"
        >
            <div class="rounded-lg bg-white p-8 shadow-xl dark:bg-gray-800">
                <div class="flex flex-col items-center space-y-4">
                    <svg class="h-16 w-16 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ trans('server/file.actions.upload.drop_files') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Upload Progress Overlay -->
        <div
            x-show="isUploading"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-gray-100/20 p-4"
        >
            <div class="rounded-lg bg-white shadow-xl dark:bg-gray-800 w-full max-w-3xl max-h-[600px] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ trans('server/file.actions.upload.uploading') }}
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Uploading <span x-text="totalFiles"></span> <span x-text="totalFiles === 1 ? 'file' : 'files'"></span>
                    </p>
                </div>

                <!-- File List Table -->
                <div class="flex-1 overflow-y-auto">
                    <div class="flex justify-center">
                        <table class="w-full max-w-5xl divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        File Name
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Size
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Progress
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <template x-for="(fileData, index) in uploadQueue" :key="index">
                                <tr>
                                    <!-- File Name -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate max-w-xs" x-text="fileData.name"></div>
                                        <div x-show="fileData.status === 'error'" class="text-xs text-red-600 dark:text-red-400 mt-1" x-text="fileData.error"></div>
                                    </td>
                                    
                                    <!-- Size -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400" x-text="formatBytes(fileData.size)"></div>
                                    </td>
                                    
                                    <!-- Progress -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center space-x-3" style="min-width: 200px;">
                                            <div x-show="fileData.status === 'uploading' || fileData.status === 'complete'" class="flex-1">
                                                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                                    <div 
                                                        class="h-2 rounded-full transition-all duration-300"
                                                        :class="fileData.status === 'complete' ? 'bg-green-500' : 'bg-primary-500'"
                                                        :style="`width: ${fileData.progress}%`"
                                                    ></div>
                                                </div>
                                                <div class="flex justify-between mt-1">
                                                    <span class="text-xs text-gray-600 dark:text-gray-400" x-text="`${fileData.progress}%`"></span>
                                                    <span x-show="fileData.status === 'uploading' && fileData.speed > 0" class="text-xs text-gray-600 dark:text-gray-400" x-text="formatSpeed(fileData.speed)"></span>
                                                </div>
                                            </div>
                                            <span x-show="fileData.status === 'pending'" class="text-sm text-gray-500 dark:text-gray-400">-</span>
                                        </div>
                                    </td>
                                    
                                    <!-- Status -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span x-show="fileData.status === 'pending'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            Pending
                                        </span>
                                        <span x-show="fileData.status === 'uploading'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            <svg class="animate-spin -ml-0.5 mr-1.5 h-3 w-3" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Uploading
                                        </span>
                                        <span x-show="fileData.status === 'complete'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            <svg class="-ml-0.5 mr-1.5 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            Complete
                                        </span>
                                        <span x-show="fileData.status === 'error'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            <svg class="-ml-0.5 mr-1.5 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                            </svg>
                                            Failed
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
