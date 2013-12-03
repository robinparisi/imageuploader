<?php

App::uses('ModelBehavior', 'Model');


class AttachmentBehavior extends ModelBehavior {

    public $settings = array();


    /**
     * Initialisation
     * @param  Model  $model    [description]
     * @param  array  $settings [description]
     */
    public function setup(Model $model, $settings = array()) {
        $defaut = array(
            'avatar' => array(
                'path' => '/avatars/',
                'transforms' => array(
                    'normal' => array(
                        'append' => '',
                        'width' => 215,
                        'height' => 215,
                    ),
                    'small' => array(
                        'append' => '-small',
                        'width' => 100,
                        'height' => 100,
                    )
                )
            )
        );

        $this->settings[$model->alias] = $settings;
    }

    /**
     * Traitement des images avant la sauvegarde
     *
     * @param Model $model
     * @return bool
     */
    public function beforeSave(Model $model) {

        // s'il y a des champs image
        $fields = array();
        foreach ($this->settings[$model->alias] as $fieldName => $field) {
            if (array_key_exists($fieldName, $model->data[$model->alias])) {
                // validation
                if ($model->data[$model->alias][$fieldName] !== null) {
                    if (! $this->checkExtension($model->data[$model->alias][$fieldName]['name'])) {
                        $model->invalidate($fieldName, __('Format de l\'image incorecte.'));
                        return false;
                    }
                    else {
                        $fields[$fieldName] = $field;
                    }
                }
            }
        }

        // si il n'y pas d'image à savegarder on accepte le save
        if (empty($fields)) {
            return true;
        }
        else {
            if (empty($model->id)) {
                return false;
            }
            $data = $model->find('first', array(
                'conditions' => array($model->alias . '.' . $model->primaryKey => $model->id),
                'recursive' => -1
            ));
        }

        // pour chaque champ du model
        foreach ($fields as $fieldName => $field) {
            $path = $field['path'];
            // pour chaque transformation
            foreach ($field['transforms'] as $transform) {
                // on supprime le fichier
                $filename = preg_replace('/(\.[^.]+)$/', sprintf('%s$1', $transform['append']), $data[$model->alias][$fieldName]);
                $filepath = $path . DIRECTORY_SEPARATOR . $filename;
                if (is_file($filepath)) {
                    unlink($filepath);
                }

                // on redimensionne la nouvelle image
                if (is_array($model->data[$model->alias][$fieldName])) {
                    $newFilename = preg_replace('/(\.[^.]+)$/', sprintf('%s$1', $transform['append']), $model->data[$model->alias][$fieldName]['new_name']);
                    $newFilepath = $path . DIRECTORY_SEPARATOR . $newFilename;
                    $this->resize($model->data[$model->alias][$fieldName]['tmp_name'], $newFilepath, array($transform['width'], $transform['height']));
                }
            }

            $model->data[$model->alias][$fieldName] = DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $model->data[$model->alias][$fieldName]['new_name'];
        }

        return true;
    }

    /**
     * Suppression des images liées au model
     *
     * @param Model $model
     * @param bool $cascade
     * @return bool
     */
    public function beforeDelete(Model $model, $cascade = true) {
        if (empty($model->id)) {
            return false;
        }

        $data = $model->find('first', array(
            'conditions' => array($model->alias . '.' . $model->primaryKey => $model->id),
            'recursive' => -1
        ));

        // pour chaque champ du model
        foreach ($this->settings[$model->alias] as $fieldName => $field) {
            // pour chaque transformation
            foreach ($field['transforms'] as $transform) {
                // on supprime le fichier
                $filename = preg_replace('/(\.[^.]+)$/', sprintf('%s$1', $transform['append']), $data[$model->alias][$fieldName]);
                $filepath = $field['path'] . DIRECTORY_SEPARATOR . $filename;
                if (is_file($filepath)) {
                    unlink($filepath);
                }
            }
        }

        return true;
    }

    public function checkExtension($name){

        $extensionsValides = array('jpg' , 'jpeg', 'png', 'gif');
        $extension = strtolower(substr(strrchr($name, '.') , 1));

        return in_array($extension, $extensionsValides);
    }

    public function checkNoErrors(){

        if(!$this->checkExtension()){
            return false;
        }

        if($this->image['error'] == UPLOAD_ERR_OK) {
            return true;
        }
        elseif ($this->image['error'] == UPLOAD_ERR_INI_SIZE || $this->image['error'] == UPLOAD_ERR_FORM_SIZE) {
            $this->error = __('Votre image est trop lourde.');
            return false;
        }
        else {
            $this->error = __('Une erreur est survenue lors tu téléchargement de l\'image');
            return false;
        }
    }

    /**
     * redimension une image
     * @return bool true s'il n'y a pas d'erreur
     */
    public function resize($sourcePath, $newPath, $options) {
        list($width, $height) = $options;

        list($sourceWidth, $sourceHeight, $sourceType) = getimagesize($sourcePath);


        switch ($sourceType) {
            case IMAGETYPE_GIF:
                $sourceGdim = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_JPEG:
                $sourceGdim = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceGdim = imagecreatefrompng($sourcePath);
                break;
        }


        // tmp size
        $sourceRatio = $sourceWidth / $sourceHeight;
        $ratio = $width / $height;

        if ($sourceRatio > $ratio) {
            $tmpHeight = $height;
            $tmpWidth = ( int ) ($height * $sourceRatio);
        }
        else {
            $tmpWidth = $width;
            $tmpHeight = ( int ) ($width / $sourceRatio);
        }

        // resizing
        $tmpGdim = imagecreatetruecolor($tmpWidth, $tmpHeight);
        imagecopyresampled(
            $tmpGdim,
            $sourceGdim,
            0, 0,
            0, 0,
            $tmpWidth, $tmpHeight,
            $sourceWidth, $sourceHeight
        );


        // croping
        $x = ($tmpWidth - $width) / 2;
        $y = ($tmpHeight - $height) / 2;

        $newGdim = imagecreatetruecolor($width, $height);

        imagecopy(
            $newGdim,
            $tmpGdim,
            0, 0,
            $x, $y,
            $width, $height
        );

        imagejpeg($newGdim, $newPath, 100);

        return false;
    }
}