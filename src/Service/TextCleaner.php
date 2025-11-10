<?php

namespace App\Service;

class TextCleaner
{
    public function clean(string $text): string
    {
        // Normalisation des apostrophes et guillemets typographiques → tapables
        $text = str_replace(
            ['’', '‘', '“', '”', '«', '»'],
            ["'", "'", '"', '"', '"', '"'],
            $text
        );

        // Supprime les (de), (en), (it), etc.
        $text = preg_replace('/\s*\([a-z]{2}\)\s*/i', '', $text);

        // Supprime les accents sur les MAJUSCULES uniquement
        $text = strtr($text, [
            'À' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'Ç' => 'C',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Î' => 'I', 'Ï' => 'I',
            'Ô' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ÿ' => 'Y'
        ]);

        // Conserve uniquement les caractères autorisés (lettres FR, ponctuation, espaces)
        $text = preg_replace(
            '/[^a-zA-Z0-9à-öø-ÿœŒæÆçÇ\'"(),.!?:;—\-–%\s]/u',
            '',
            $text
        );

        // Simplifie les espaces multiples
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
