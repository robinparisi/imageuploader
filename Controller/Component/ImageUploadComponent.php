<?php

class ImageUploadComponent extends Component {


    public  $image;

    private $extension,
            $error = false,
            $json = array(),
            $fullPath = false;



    public function checkExtension(){

        $extensions_valides = array( 'jpg' , 'jpeg', 'png', 'gif');
        $extension_upload = strtolower(substr(strrchr($this->image['name'], '.') , 1));

        $this->extension = $extension_upload;

        // Extension incorrecte
        if (! in_array($extension_upload, $extensions_valides)) {
            $this->error = __('Format de l\'image incorecte.');
            return false;
        } else {
            return true;
        }
    }

    public function checkNoErrors(){

        if(!$this->checkExtension()){
            return false;
        }

        if($this->image['error'] == UPLOAD_ERR_OK){
            return true;
        } elseif ($this->image['error'] == UPLOAD_ERR_INI_SIZE || $this->image['error'] == UPLOAD_ERR_FORM_SIZE) {
            $this->error = __('Votre image est trop lourde.');
            return false;
        }
        else {
            $this->error = __('Une erreur est survenue lors tu téléchargement de l\'image');
            return false;
        }
    }


    public function response($controller, $redirect){

        if($controller->request->is('ajax')){

            $controller->autoRender = false;

            if ($this->fullPath) {

                $this->json['success'] = true;
                $this->json['url'] = $this->fullPath;

            } else {

                $this->json['success'] = false;
                $this->json['error'] = $this->error;

            }

            echo json_encode($this->json);

        } else {

            if ($this->error) {
                $controller->Session->setFlash($this->error, 'default', array('class' => 'error'));
            }

            $controller->redirect($redirect);
        }

    }


    public function getExtension(){
        return $this->extension;
    }

    public function getError(){
        return $this->getError();
    }

    public function setCustomError($error){
        $this->error = $error;
    }

    public function setFullPath($fullPath){
        $this->fullPath = $fullPath;
    }
}