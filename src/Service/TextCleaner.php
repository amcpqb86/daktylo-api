<?php

namespace App\Service;

class TextCleaner
{
    public function clean(string $text): string
    {
        // 1. Décoder les entités HTML
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. Normalisation des apostrophes et guillemets typographiques → tapables
        $text = str_replace(
            ['’', '‘', '“', '”', '«', '»'],
            ["'", "'", '"', '"', '"', '"'],
            $text
        );

        // 3. Normaliser les espaces spéciaux → espace simple
        $text = str_replace(["\xc2\xa0", "\xe2\x80\xaf", "\xe2\x80\xa8", "\xe2\x80\xa9"], ' ', $text);

        // 4. Ellipse
        $text = str_replace(['…'], '...', $text);

        // 5. Ligatures → formes tapables
        $text = str_replace(['œ', 'Œ'], 'oe', $text);
        $text = str_replace(['æ', 'Æ'], 'ae', $text);

        // 6. Tous les types de tirets → tiret simple
        $text = str_replace(
            ['–', '—', '−', '‒'],
            '-',
            $text
        );
        // éviter les -- après remplacement
        $text = preg_replace('/-{2,}/', '-', $text);

        // 7. Retours à la ligne → espace
        $text = preg_replace("/\r\n|\r|\n/", ' ', $text);

        // 8. Supprime les (de), (en), (it), etc.
        $text = preg_replace('/\s*\([a-z]{2}\)\s*/i', '', $text);

        // 9. Supprime les accents sur les MAJUSCULES uniquement
        $text = strtr($text, [
            'À' => 'A',
            'Ç' => 'C',
            'É' => 'E', 'È' => 'E',
            'Ù' => 'U',
            'Ÿ' => 'Y'
        ]);

        // 10. Conserver uniquement les caractères autorisés
        $text = preg_replace(
            '/[^a-zA-Z0-9à-öø-ÿçÇ\'"(),.!?:;\-%\s]/u',
            '',
            $text
        );

        // 11. Simplifier les espaces
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
