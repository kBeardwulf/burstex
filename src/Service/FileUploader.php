<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Classe de service qui permet de regrouper toutes les fonctionnalités 
 * liées à l'enregistrement (upload) de fichiers
 */
class FileUploader
{
    public function __construct(
        private string $pictureDirectory,        //< Injection de dépendance par Symfony, à partir du fichier config/services.yaml
        private Filesystem $fileSystem,
    )
    {
        
    }

    /**
     * Récupère un fichier uploadé, rend unique le nom du fichier final et l'enregistre au bon emplacement
     */
    public function upload(UploadedFile $file): string
    {
        // On récupère le nom du fichier sans l'extension (.png, .jpg...)
        $strBasefileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        // On accolle uniqid pour rendre unique le nom + l'extension du fichier (PNG, JPG...)
        $strNewFilename = $strBasefileName . uniqid() . '.' . $file->guessExtension();

        // Déplace le fichier dans le répertoire /public/uploads/pictures
        $file->move($this->pictureDirectory, $strNewFilename);

        return $strNewFilename; //< Renvoi le nouveau nom du fichier
    }

    /**
     * Suppprimer le fichier sur le disque
     */
    public function remove(string $filename): void
    {
        // remove va supprimer physiquement le fichier sur le disque à un emplacement indiqué
        $this->fileSystem->remove($this->pictureDirectory . '/' . $filename);
    }
}