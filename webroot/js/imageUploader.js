(function($) {

    $.fn.imageUploader = function(options) {

        var defaults = {
            name: null,
            defaultImage: null
        };

        var params = $.extend(defaults, options);

        this.each(function() {
            if ($(this).data('ImageUploader') === undefined) {
                var uploader = new Uploader($(this), params);
                uploader.init();

                $(this).data('ImageUploader', uploader);
            }
        });

        return this;
    };



/**
 * Uploader class
 */
function Uploader(self, options) {

    // form
    var form = null;
    var fileInput = null;
    var submitButton = null;
    var deleteButton = null;

    // progress bar
    var progressBarContainer = null;
    var progressBar = null;

    var errorDiv = null;

    this.init = init;

    function init() {
        var name = '';
        if (options.name !== null) {
            name = options.name;
        }
        else if (self.attr('data-name')) {
            name = self.attr('data-name');
        }

        form = self.next('form');

        fileInput = form.find('input[type=file]');

        submitButton = form.find('input[type=submit]');

        deleteButton = form.next('a');

        deleteButton.click(function(ev) {
            ev.preventDefault();

            if (confirm('Êtes vous sûre de vouloir supprimer cette image ?')) {
                $.ajax({
                    url: $(this).attr('href'),
                    dataType: 'json',
                    success: function(data) {
                        self.attr('src', options.defaultImage);
                    }
                });
            }
        });

        initProgressBar();
        initUpload();
    }

    // init progress bar
    function initProgressBar() {
        progressBarContainer = $('<div>', {
            class: 'iu-progress-bar-container'
        });

        progressBar = $('<div>', {
            class: 'iu-progress-bar'
        });
        progressBarContainer.append(progressBar);

        errorDiv = $('<div>', {
            class: 'error'
        });
    }

    function initUpload() {
        // test la compatibilité js
        if (window.FileReader && window.FormData) {

            // supprimer le bouton submit
            submitButton.css('display', 'none');

            // à la selection du fichier
            fileInput.change(function() {
                var formdata = new FormData();

                if (this.files.length > 0) {
                    var file = this.files[0];

                    if (!!file.type.match(/image.*/)) {

                        // on lit le fichier
                        var reader = new FileReader();
                        reader.readAsDataURL(file);
                        formdata.append(fileInput.attr('name'), file);

                        // affiche la progress bar
                        self.after(progressBarContainer);

                        $.ajax({
                            xhr: function() {
                                var xhr = new window.XMLHttpRequest();

                                // update progress bar
                                xhr.upload.addEventListener('progress', function(evt){
                                    if (evt.lengthComputable) {
                                        var percentComplete = evt.loaded / evt.total * 100;
                                        console.log(percentComplete);

                                        progressBar.css('width', percentComplete + '%');
                                    }
                                }, false);

                                return xhr;
                            },
                            url: form.attr('action'),
                            type: 'POST',
                            data: formdata,
                            dataType: 'json',
                            processData: false,
                            contentType: false,
                            success: function (data) {
                                if (data.success) {
                                    self.attr('src', data.url);
                                    errorDiv.remove();
                                }
                                else {
                                    errorDiv.text(data.error);
                                    self.after(errorDiv);
                                }
                                progressBarContainer.remove();
                            }
                        });

                    }
                }

            });
        }
    }
} // end class Uploader

})(jQuery);
