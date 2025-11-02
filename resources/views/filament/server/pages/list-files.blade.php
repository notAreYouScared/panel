<x-filament-panels::page>
    <div
        x-data="{
            isDragging: false,
            dragCounter: 0,
            isUploading: false,
            uploadProgress: 0,
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

                    // Get upload URL from Livewire
                    const uploadUrl = await $wire.getUploadUrl();

                    // Upload each file directly to Wings
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        const formData = new FormData();
                        formData.append('files', file);

                        const response = await fetch(uploadUrl + '&directory=' + encodeURIComponent(@js($this->path)), {
                            method: 'POST',
                            body: formData
                        });

                        if (!response.ok) {
                            throw new Error('Upload failed for file: ' + file.name);
                        }

                        this.uploadProgress = Math.round(((i + 1) / files.length) * 100);
                    }

                    // Refresh the page to show new files
                    window.location.reload();

                } catch (error) {
                    console.error('Upload failed:', error);
                    
                    // Show error notification using Filament's notification system
                    window.dispatchEvent(new CustomEvent('notify', {
                        detail: {
                            message: '{{ trans('server/file.actions.upload.failed') }}',
                            type: 'danger'
                        }
                    }));
                } finally {
                    this.isUploading = false;
                    this.uploadProgress = 0;
                }
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
                    <p class="text-sm text-gray-600 dark:text-gray-400" x-text="`${uploadProgress}%`"></p>
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
