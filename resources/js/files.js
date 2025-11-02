/**
 * File manager drag and drop upload functionality
 */
window.initFileUpload = function (getUploadUrl, currentPath, translations, refreshCallback) {
    return {
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

                // Get upload URL
                const uploadUrl = await getUploadUrl();

                // Build URL with proper parameter handling
                const url = new URL(uploadUrl);
                url.searchParams.append('directory', currentPath);

                // Upload all files in a single request
                const formData = new FormData();
                for (let i = 0; i < files.length; i++) {
                    formData.append('files', files[i]);
                }

                const response = await fetch(url.toString(), {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Upload failed with status: ' + response.status);
                }

                this.uploadProgress = 100;

                // Refresh the component to show new files
                if (refreshCallback) {
                    await refreshCallback();
                }

                // Show success notification
                new window.FilamentNotification()
                    .title(translations.success)
                    .success()
                    .send();

            } catch (error) {
                console.error('Upload failed:', error);
                
                // Show error notification
                new window.FilamentNotification()
                    .title(translations.failed)
                    .danger()
                    .send();
            } finally {
                this.isUploading = false;
                this.uploadProgress = 0;
            }
        }
    };
};
