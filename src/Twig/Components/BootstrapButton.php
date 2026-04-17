<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class BootstrapButton
{
    private string $_strText;
    private string $_strType = ""; // Initialise à une chaine vide dans le cas où outlined = false
    private string $_strLink;

    /**
     * Monte le composant de bouton Bootstrap sur la vue HTML
     * 
     * @param string $text Texte affiché dans le bouton
     * @param string $type Type de bouton : success, primary, warning, info, secondary, danger, light, dark
     * @param string $link Lien URL de la base <a>
     * @param bool $outlined Défini si le bouton est sans fond ou avec fond
     */
    public function mount(string $text, string $type, string $link, bool $outlined = false): void
    {
        $this->_strText = $text;        
        $this->_strLink = $link;

        if($outlined) {
            $this->_strType = 'outline-';
        }

        $this->_strType .= $type;
    }

    /**
     * Retourne le texte affiché dans le bouton
     * 
     * @return string
     */
    public function getText(): string
    {
        return $this->_strText;
    }

    /**
     * Retourne la classe Bootstrap
     * 
     * @return string
     */
    public function getType(): string
    {
        return $this->_strType;
    }

    /**
     * Retourne le lien (URL) associé à la balise
     * 
     * @return string
     */
    public function getLink(): string
    {
        return $this->_strLink;
    }
}