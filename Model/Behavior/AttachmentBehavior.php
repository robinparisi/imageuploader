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

        // s'il y a des champs images
        $fields = array();
        foreach ($this->settings[$model->alias] as $fieldName => $field) {
            if (array_key_exists($fieldName, $model->data[$model->alias])) {
                // suppresion des images
                if ($model->data[$model->alias][$fieldName] === null) {
                    $fields[$fieldName] = $field;
                }
                // aucune image, aucune modification
                elseif ($model->data[$model->alias][$fieldName]['error'] != 0) {
                    unset($model->data[$model->alias][$fieldName]);
                }
                else {
                    // validation
                    if (! $this->checkErrors($model, $fieldName)) {
                        return false;
                    }
                    else {
                        $fields[$fieldName] = $field;
                    }
                }
            }
        }


        $data = null;
        if (! empty($model->id)) {
            $data = $model->find('first', array(
                'conditions' => array($model->alias . '.' . $model->primaryKey => $model->id),
                'recursive' => -1
            ));
        }

        // pour chaque champ du model
        foreach ($fields as $fieldName => $field) {
            $path = $field['path'];
            $filename = pathinfo($model->data[$model->alias][$fieldName]['name'], PATHINFO_FILENAME);
            $extension = '.jpg';

            // pour chaque transformation
            foreach ($field['transforms'] as $transform) {
                // on supprime les anciens fichiers
                if ($data) {
                    // 1 pour enlever le slash -4 = taille de .jpg
                    $file = substr($data[$model->alias][$fieldName], 1, -4) . $transform['append'] . $extension;
                    if (is_file($file)) {
                        unlink($file);
                    }
                }

                // on redimensionne la nouvelle image
                if (is_array($model->data[$model->alias][$fieldName])) {
                    $filepath = $path . '/' . $filename . $transform['append'] . $extension;
                    $this->resize($model->data[$model->alias][$fieldName]['tmp_name'], $filepath, $transform['width'], $transform['height']);
                }
            }

            if (! empty($model->data[$model->alias][$fieldName])) {
                $model->data[$model->alias][$fieldName] =  '/' . $path . '/' . $filename . $extension;
            }
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
                $filename = substr($filename, 1);
                if (is_file($filename)) {
                    unlink($filename);
                }
            }
        }

        return true;
    }

    /**
     * Check le type d'image. Les fichiers acceptés sont gif, jpg, png
     *
     * @param  string   $path  chemin du fichier à tester
     * @return bool
     */
    public function checkSourceType($path) {
        $ok = true;
        list($sourceWidth, $sourceHeight, $sourceType) = getimagesize($path);

        switch ($sourceType) {
            case IMAGETYPE_GIF:
                break;
            case IMAGETYPE_JPEG:
                break;
            case IMAGETYPE_PNG:
                break;
            default:
                $ok = false;
                break;
        }

        return $ok;
    }

    /**
     * Validation d'un champ dans un model
     *
     * @param  Model  $model    Model
     * @param  string $filename Nom du champs à valider
     * @return bool
     */
    public function checkErrors($model, $fieldName) {
        $image = $model->data[$model->alias][$fieldName];

        if(!$this->checkSourceType($image['tmp_name'])) {
            $model->invalidate($fieldName, __('L\'image doit être de type jpg, png ou gif'));
            return false;
        }

        if($image['error'] == UPLOAD_ERR_OK) {
            return true;
        }
        elseif ($image['error'] == UPLOAD_ERR_INI_SIZE || $image['error'] == UPLOAD_ERR_FORM_SIZE) {
            $model->invalidate($fieldName, __('Votre image est trop lourde.'));
            return false;
        }
        else {
            $model->invalidate($fieldName, __('Une erreur est survenue lors tu téléchargement de l\'image'));
            return false;
        }

        return false;
    }

    /**
     * redimension une image
     * @return bool true s'il n'y a pas d'erreur
     */
    public function resize($sourcePath, $newPath, $width, $height) {
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
