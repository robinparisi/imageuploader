<?php
App::uses('Helper', 'View');

class ImageHelper extends Helper {
    var $helpers = array('Html', 'Form');

    public function avatar($image, $options = array()) {

        if (empty($image)) {
            $gravatarLink = 'http://www.gravatar.com/avatar/00000000000000000000000000000000?s=100&d=mm';
            $html = $this->Html->image($gravatarLink, $options);
        }
        else {
            $html = $this->Html->image('/avatars/'.$image, $options);
        }

        return $html;
    }

    public function form($name, $image, $uploadURL, $deleteURL, $options = array()) {
        $imageOptions = array();
        if (array_key_exists('id', $options)) {
            $imageOptions['id'] = $options['id'];
        }

        $label = __('Upload');
        if (array_key_exists('label', $options)) {
            $label = $options['label'];
        }

        $html  = '<div class="iu-container">';
        $html .= $this->avatar($image, $imageOptions);
        $html .= $this->Form->create($uploadURL['controller'], array('type' => 'file', 'action' => $uploadURL['action']));
        $html .= '<span class="iu-button">';
        $html .= $label;
        $html .= $this->Form->input($name, array('type' => 'file', 'div' => false, 'label' => false));
        $html .= '</span>';
        $html .= $this->Form->end(array('label' => 'Envoyer'));
        $html .= $this->Html->link('Suppimer', $deleteURL, array('class' => 'iu-delete'));
        $html .= '</div>';

        return $html;
    }


}