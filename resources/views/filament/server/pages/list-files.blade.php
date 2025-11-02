<x-filament-panels::page>
    <div
        x-data="{
            isDragging: false,
            dragCounter: 0,
            isUploading: false,
            uploadQueue: [],
            currentFileIndex: 0,
            totalFiles: 0,
            removeFile(index) {
                if (this.uploadQueue[index].status === 'uploading') {
                    // Don't allow removing files that are currently uploading
                    return;
                }
                this.uploadQueue.splice(index, 1);
                this.totalFiles = this.uploadQueue.length;
                if (this.uploadQueue.length === 0) {
                    this.isUploading = false;
                }
            },
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
                    setTimeout(() => {
                        this.isUploading = false;
                        this.uploadQueue = [];
                    }, 500);
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
                class="rounded-lg bg-white shadow-xl dark:bg-gray-800 w-full max-w-3xl max-h-[600px] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ trans('server/file.actions.upload.uploading') }}
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        <span x-text="currentFileIndex"></span> of <span x-text="totalFiles"></span> <span
                            x-text="totalFiles === 1 ? 'file' : 'files'"></span> completed
                    </p>
                </div>

                <!-- File List Table -->
                <div class="flex-1 overflow-y-auto">
                    <div class="flex justify-center">
                        <table class="table-auto">
                            <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    File Name
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Size
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Progress
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <template x-for="(fileData, index) in uploadQueue" :key="index">
                                <tr>
                                    <!-- File Name -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div
                                            class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate max-w-xs"
                                            x-text="fileData.name"></div>
                                        <div x-show="fileData.status === 'error'"
                                             class="text-xs text-red-600 dark:text-red-400 mt-1"
                                             x-text="fileData.error"></div>
                                    </td>

                                    <!-- Size -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400"
                                             x-text="formatBytes(fileData.size)"></div>
                                    </td>

                                    <!-- Progress -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center space-x-3" style="min-width: 200px;">
                                            <div
                                                x-show="fileData.status === 'uploading' || fileData.status === 'complete'"
                                                class="flex-1">
                                                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                                    <div
                                                        class="h-2 rounded-full transition-all duration-300"
                                                        :class="fileData.status === 'complete' ? 'bg-green-500' : 'bg-primary-500'"
                                                        :style="`width: ${fileData.progress}%`"
                                                    ></div>
                                                </div>
                                                <div class="flex justify-between mt-1">
                                                    <span class="text-xs text-gray-600 dark:text-gray-400"
                                                          x-text="`${fileData.progress}%`"></span>
                                                    <span x-show="fileData.status === 'uploading' && fileData.speed > 0"
                                                          class="text-xs text-gray-600 dark:text-gray-400"
                                                          x-text="formatSpeed(fileData.speed)"></span>
                                                </div>
                                            </div>
                                            <span x-show="fileData.status === 'pending'"
                                                  class="text-sm text-gray-500 dark:text-gray-400">-</span>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span x-show="fileData.status === 'pending'"
                                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            Pending
                                        </span>
                                        <span x-show="fileData.status === 'uploading'"
                                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            <svg class="animate-spin -ml-0.5 mr-1.5 h-3 w-3" fill="none"
                                                 viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                        stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Uploading
                                        </span>
                                        <span x-show="fileData.status === 'complete'"
                                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            <svg class="-ml-0.5 mr-1.5 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                      clip-rule="evenodd" />
                                            </svg>
                                            Complete
                                        </span>
                                        <span x-show="fileData.status === 'error'"
                                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            <svg class="-ml-0.5 mr-1.5 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                      d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                      clip-rule="evenodd" />
                                            </svg>
                                            Failed
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <button
                                            x-show="fileData.status === 'pending' || fileData.status === 'error'"
                                            @click="removeFile(index)"
                                            type="button"
                                            class="inline-flex items-center p-1 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                            title="Remove file"
                                        >
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                                      clip-rule="evenodd"/>
                                            </svg>
                                        </button>
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
