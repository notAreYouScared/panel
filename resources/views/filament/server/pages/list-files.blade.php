<x-filament-panels::page>
    <div
        x-data="{
            isDragging: false,
            dragCounter: 0,
            isUploading: false,
            uploadProgress: 0,
            uploadSpeed: 0,
            uploadedBytes: 0,
            totalBytes: 0,
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
                try {
                    this.isUploading = true;
                    this.uploadProgress = 0;
                    this.uploadSpeed = 0;
                    this.uploadedBytes = 0;
                    this.totalBytes = 0;

                    // Calculate total bytes
                    for (let i = 0; i < files.length; i++) {
                        this.totalBytes += files[i].size;
                    }

                    // Get upload URL from Livewire
                    const uploadUrl = await $wire.getUploadUrl();

                    // Build URL with proper parameter handling (once, outside the loop)
                    const url = new URL(uploadUrl);
                    url.searchParams.append('directory', @js($this->path));

                    // Upload all files in a single request
                    const formData = new FormData();
                    for (let i = 0; i < files.length; i++) {
                        formData.append('files', files[i]);
                    }

                    // Use XMLHttpRequest for progress tracking
                    await new Promise((resolve, reject) => {
                        const xhr = new XMLHttpRequest();
                        let startTime = Date.now();
                        let lastLoaded = 0;
                        let lastTime = startTime;

                        xhr.upload.addEventListener('progress', (e) => {
                            if (e.lengthComputable) {
                                this.uploadedBytes = e.loaded;
                                this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                                
                                // Calculate upload speed
                                const currentTime = Date.now();
                                const timeDiff = (currentTime - lastTime) / 1000; // seconds
                                if (timeDiff > 0.1) { // Update speed every 100ms
                                    const bytesDiff = e.loaded - lastLoaded;
                                    this.uploadSpeed = bytesDiff / timeDiff; // bytes per second
                                    lastTime = currentTime;
                                    lastLoaded = e.loaded;
                                }
                            }
                        });

                        xhr.addEventListener('load', () => {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                resolve();
                            } else {
                                reject(new Error('Upload failed with status: ' + xhr.status));
                            }
                        });

                        xhr.addEventListener('error', () => {
                            reject(new Error('Upload failed'));
                        });

                        xhr.addEventListener('abort', () => {
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
                    
                    // Show error notification using Filament's notification system
                    new window.FilamentNotification()
                        .title('{{ trans('server/file.actions.upload.failed') }}')
                        .danger()
                        .send();
                } finally {
                    this.isUploading = false;
                    this.uploadProgress = 0;
                    this.uploadSpeed = 0;
                    this.uploadedBytes = 0;
                    this.totalBytes = 0;
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
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-gray-100/20"
        >
            <div class="rounded-lg bg-white p-8 shadow-xl dark:bg-gray-800">
                <div class="flex flex-col items-center space-y-4">
                    <svg class="h-16 w-16 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ trans('server/file.actions.upload.uploading') }}
                    </p>
                    <div class="w-64 bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                        <div class="bg-primary-500 h-2.5 rounded-full transition-all duration-300" :style="`width: ${uploadProgress}%`"></div>
                    </div>
                    <div class="flex flex-col items-center space-y-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="`${uploadProgress}%`"></p>
                        <p class="text-xs text-gray-600 dark:text-gray-400" x-show="uploadSpeed > 0" x-text="`${formatBytes(uploadedBytes)} / ${formatBytes(totalBytes)}`"></p>
                        <p class="text-xs text-gray-600 dark:text-gray-400" x-show="uploadSpeed > 0" x-text="`${formatSpeed(uploadSpeed)}`"></p>
                    </div>
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
