<x-filament-panels::page>
    <div
        x-data="{
            isDragging: false,
            dragCounter: 0,
            isUploading: false,
            uploadQueue: [],
            currentFileIndex: 0,
            totalFiles: 0,
            autoCloseTimer: null,
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
                this.currentFileIndex = 0;

                try {
                    // Get upload size limit from server
                    const uploadSizeLimit = await $wire.getUploadSizeLimit();

                    // Validate file sizes before uploading
                    for (let i = 0; i < files.length; i++) {
                        if (files[i].size > uploadSizeLimit) {
                            new window.FilamentNotification()
                                .title(`File ${files[i].name} exceeds the upload size limit of ${this.formatBytes(uploadSizeLimit)}`)
                                .danger()
                                .send();
                            this.isUploading = false;
                            return;
                        }
                    }

                    // Initialize queue with file metadata
                    for (let i = 0; i < files.length; i++) {
                        this.uploadQueue.push({
                            file: files[i],
                            name: files[i].name,
                            size: files[i].size,
                            progress: 0,
                            speed: 0,
                            uploadedBytes: 0,
                            status: 'pending',
                            error: null
                        });
                    }

                    // Upload files concurrently (max 3 at a time)
                    // Each file gets its own token
                    const maxConcurrent = 3;
                    let activeUploads = [];
                    let completedCount = 0;

                    for (let i = 0; i < files.length; i++) {
                        // Start upload (will get its own token)
                        const uploadPromise = this.uploadFile(i)
                            .then(() => {
                                completedCount++;
                                this.currentFileIndex = completedCount;
                            })
                            .catch((error) => {
                                completedCount++;
                                this.currentFileIndex = completedCount;
                                console.error(`Failed to upload ${this.uploadQueue[i].name}:`, error);
                            });

                        activeUploads.push(uploadPromise);

                        // Wait if we hit the concurrent limit
                        if (activeUploads.length >= maxConcurrent) {
                            await Promise.race(activeUploads);
                            activeUploads = activeUploads.filter(p => {
                                // Check if promise is still pending
                                let isPending = true;
                                p.then(() => { isPending = false; }).catch(() => { isPending = false; });
                                return isPending;
                            });
                        }
                    }

                    // Wait for all remaining uploads to complete
                    await Promise.allSettled(activeUploads);

                    // Check results
                    const failedUploads = this.uploadQueue.filter(f => f.status === 'error');

                    // Refresh the component to show new files
                    await $wire.$refresh();

                    // Show appropriate notification
                    if (failedUploads.length === 0) {
                        new window.FilamentNotification()
                            .title('{{ trans('server/file.actions.upload.success') }}')
                            .success()
                            .send();
                    } else if (failedUploads.length < this.totalFiles) {
                        new window.FilamentNotification()
                            .title(`${this.totalFiles - failedUploads.length} of ${this.totalFiles} files uploaded successfully`)
                            .warning()
                            .send();
                    } else {
                        new window.FilamentNotification()
                            .title('{{ trans('server/file.actions.upload.failed') }}')
                            .danger()
                            .send();
                    }

                } catch (error) {
                    console.error('Upload failed:', error);
                    new window.FilamentNotification()
                        .title('{{ trans('server/file.actions.upload.failed') }}')
                        .danger()
                        .send();
                } finally {
                    // Auto-close after 5 seconds when all uploads complete
                    this.autoCloseTimer = setTimeout(() => {
                        this.closeUploadDialog();
                    }, 5000);
                }
            },
            async uploadFile(index) {
                const fileData = this.uploadQueue[index];
                fileData.status = 'uploading';

                try {
                    // Get a fresh token for this file
                    const uploadUrl = await $wire.getUploadUrl();
                    const url = new URL(uploadUrl);
                    url.searchParams.append('directory', @js($this->path));

                    return new Promise((resolve, reject) => {
                        const xhr = new XMLHttpRequest();
                        const formData = new FormData();
                        formData.append('files', fileData.file);

                        let lastLoaded = 0;
                        let lastTime = Date.now();

                        xhr.upload.addEventListener('progress', (e) => {
                            if (e.lengthComputable) {
                                fileData.uploadedBytes = e.loaded;
                                fileData.progress = Math.round((e.loaded / e.total) * 100);

                                // Calculate upload speed
                                const currentTime = Date.now();
                                const timeDiff = (currentTime - lastTime) / 1000;
                                if (timeDiff > 0.1) {
                                    const bytesDiff = e.loaded - lastLoaded;
                                    fileData.speed = bytesDiff / timeDiff;
                                    lastTime = currentTime;
                                    lastLoaded = e.loaded;
                                }
                            }
                        });

                        xhr.addEventListener('load', () => {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                fileData.status = 'complete';
                                fileData.progress = 100;
                                resolve();
                            } else {
                                fileData.status = 'error';
                                fileData.error = `Upload failed (${xhr.status})`;
                                reject(new Error(fileData.error));
                            }
                        });

                        xhr.addEventListener('error', () => {
                            fileData.status = 'error';
                            fileData.error = 'Network error';
                            reject(new Error('Upload failed'));
                        });

                        xhr.addEventListener('abort', () => {
                            fileData.status = 'error';
                            fileData.error = 'Upload cancelled';
                            reject(new Error('Upload aborted'));
                        });

                        xhr.open('POST', url.toString());
                        xhr.send(formData);
                    });
                } catch (error) {
                    fileData.status = 'error';
                    fileData.error = 'Failed to get upload token';
                    throw error;
                }
            },
            formatBytes(bytes) {
                if (bytes === 0) return '0.00 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
            },
            formatSpeed(bytesPerSecond) {
                return this.formatBytes(bytesPerSecond) + '/s';
            },
            closeUploadDialog() {
                if (this.autoCloseTimer) {
                    clearTimeout(this.autoCloseTimer);
                    this.autoCloseTimer = null;
                }
                this.isUploading = false;
                this.uploadQueue = [];
            },
            handleEscapeKey(e) {
                if (e.key === 'Escape' && this.isUploading) {
                    this.closeUploadDialog();
                }
            }
        }"
        @dragenter.window="handleDragEnter($event)"
        @dragleave.window="handleDragLeave($event)"
        @dragover.window="handleDragOver($event)"
        @drop.window="handleDrop($event)"
        @keydown.window="handleEscapeKey($event)"
        class="relative"
    >
        <!-- Drag & Drop Overlay -->
        <div
            x-show="isDragging"
            x-cloak
            x-transition:enter="transition-[opacity] duration-200 ease-out"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-[opacity] duration-150 ease-in"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-gray-100/20"
        >
            <div class="rounded-lg bg-white p-8 shadow-xl dark:bg-gray-800">
                <div class="flex flex-col items-center gap-4">
                    <svg class="size-16 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
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
            <div
                class="rounded-lg bg-white shadow-xl dark:bg-gray-800 w-1/2 max-h-[50vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-center">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ trans('server/file.actions.upload.uploading') }} - <span class="text-sm text-gray-600 dark:text-gray-400"><span x-text="currentFileIndex"></span> of <span x-text="totalFiles"></span> <span
                                x-text="totalFiles === 1 ? 'file' : 'files'"></span> completed</span>
                    </h3>
                </div>

                <!-- File List Table (Filament-styled) -->
                <div class="flex-1 overflow-y-auto">
                    <div class="overflow-hidden">
                        <table class="w-full divide-y divide-gray-200 dark:divide-white/5">
                            <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                            <tr>
                                <th scope="col"
                                    class="px-4 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:px-6">
                                    <span class="group inline-flex items-center gap-x-1">File Name</span>
                                </th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:px-6">
                                    <span class="group inline-flex items-center gap-x-1">Size</span>
                                </th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:px-6">
                                    <span class="group inline-flex items-center gap-x-1">Progress</span>
                                </th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:px-6">
                                    <span class="group inline-flex items-center gap-x-1">Status</span>
                                </th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5 bg-white dark:bg-gray-900">
                            <template x-for="(fileData, index) in uploadQueue" :key="index">
                                <tr class="transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                                    <!-- File Name -->
                                    <td class="px-4 py-4 sm:px-6">
                                        <div class="flex flex-col gap-y-1">
                                            <div
                                                class="text-sm font-medium leading-6 text-gray-950 dark:text-white truncate max-w-xs"
                                                x-text="fileData.name"></div>
                                            <div x-show="fileData.status === 'error'"
                                                 class="text-xs text-danger-600 dark:text-danger-400"
                                                 x-text="fileData.error"></div>
                                        </div>
                                    </td>

                                    <!-- Size -->
                                    <td class="px-4 py-4 sm:px-6">
                                        <div class="text-sm text-gray-500 dark:text-gray-400"
                                             x-text="formatBytes(fileData.size)"></div>
                                    </td>

                                    <!-- Progress -->
                                    <td class="px-4 py-4 sm:px-6">
                                        <div x-show="fileData.status === 'uploading' || fileData.status === 'complete'" class="flex justify-between items-center text-sm">
                                            <span class="font-medium text-gray-700 dark:text-gray-300" x-text="`${fileData.progress}%`"></span>
                                            <span x-show="fileData.status === 'uploading' && fileData.speed > 0"
                                                  class="text-gray-500 dark:text-gray-400"
                                                  x-text="formatSpeed(fileData.speed)"></span>
                                        </div>
                                        <span x-show="fileData.status === 'pending'" class="text-sm text-gray-500 dark:text-gray-400">—</span>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-4 py-4 sm:px-6">
                                        <span x-show="fileData.status === 'pending'" class="flex items-center gap-x-2">
                                            <span class="relative flex size-2">
                                                <span class="absolute inline-flex size-full rounded-full bg-gray-400 opacity-75"></span>
                                                <span class="relative inline-flex size-2 rounded-full bg-gray-500"></span>
                                            </span>
                                        </span>
                                        <span x-show="fileData.status === 'uploading'" class="flex items-center gap-x-2">
                                            <span class="relative flex size-2">
                                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-primary-400 opacity-75"></span>
                                                <span class="relative inline-flex size-2 rounded-full bg-primary-500"></span>
                                            </span>
                                        </span>
                                            Uploading
                                        </span>
                                        <span x-show="fileData.status === 'complete'"
                                              class="inline-flex items-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-1.5 py-0.5 bg-success-50 text-success-700 ring-success-600/10 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30">
                                            <svg class="size-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            Complete
                                        </span>
                                        <span x-show="fileData.status === 'error'"
                                              class="inline-flex items-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-1.5 py-0.5 bg-danger-50 text-danger-700 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30">
                                            <svg class="size-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
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
