<?php

namespace App\Service;

class TextCleaner
{
    private function debug(string $label, string $text, $debug): void
    {
        if ($debug)
        {
            file_put_contents(
                '/tmp/clean_debug.log',
                "=== $label ===\n$text\n\n",
                FILE_APPEND
            );
        }
    }

    public function clean(string $text, $debug = false): string
    {
        $this->debug('START', $text, $debug);

        // 0. Normaliser les combinaisons (ex: i + U+0301 → í)
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C);
        }
        $this->debug('AFTER NORMALIZE', $text, $debug);

        // 1. Décoder les entités HTML
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->debug('AFTER HTML ENTITIES', $text, $debug);

        // 2. Normalisation des apostrophes et guillemets typographiques → tapables
        $text = str_replace(
            ['’', '‘', '“', '”', '«', '»'],
            ["'", "'", '"', '"', '"', '"'],
            $text
        );
        $this->debug('AFTER STEP 2', $text, $debug);

        // 3. Normaliser les espaces spéciaux → espace simple
        $text = str_replace(["\xc2\xa0", "\xe2\x80\xaf", "\xe2\x80\xa8", "\xe2\x80\xa9"], ' ', $text);
        $this->debug('AFTER STEP 3', $text, $debug);

        // 4. Ellipse
        $text = str_replace(['…'], '...', $text);
        $this->debug('AFTER STEP 4', $text, $debug);

        // 5. Ligatures → formes tapables
        $text = str_replace(['œ', 'Œ'], 'oe', $text);
        $text = str_replace(['æ', 'Æ'], 'ae', $text);
        $this->debug('AFTER STEP 5', $text, $debug);

        // 6. Tous les types de tirets → tiret simple
        $text = str_replace(
            ['–', '—', '−', '‒'],
            '-',
            $text
        );
        // éviter les -- après remplacement
        $text = preg_replace('/-{2,}/', '-', $text);
        $this->debug('AFTER STEP 6', $text, $debug);


        // 7. Retours à la ligne → espace
        $text = preg_replace("/\r\n|\r|\n/", ' ', $text);
        $this->debug('AFTER STEP 7', $text, $debug);

        // 7b. Supprimer les transcriptions phonétiques et autres blocs entre crochets
        // - tout ce qui est entre [ ... ] avec des lettres/IPA
        $text = preg_replace('/\[[^\]]*[^\d\s][^\]]*\]/u', '', $text);
        // - puis les références [1], [12]...
        $text = preg_replace('/\[\d+\]/u', '', $text);
        $this->debug('AFTER STEP 7B BRACKETS', $text, $debug);

        // 7c. Supprimer les "Portail de ..." en fin d'extrait
        $text = preg_replace('/Portail [^.]+$/u', '', $text);
        $this->debug('AFTER STEP 7C PORTAL', $text, $debug);

        // 8. Supprime les (de), (en), (it), etc.
        $text = preg_replace('/\s*\([a-z]{2}\)\s*/i', '', $text);
        $this->debug('AFTER STEP 8', $text, $debug);

        // 9. Supprime les accents sur les MAJUSCULES uniquement
        $protect = [
            // minuscules uniquement : on les protège pour garder leurs accents
            'é'=>'__E_ACUTE__','è'=>'__E_GRAVE__','ê'=>'__E_CIRC__','ë'=>'__E_DIER__',
            'à'=>'__A_GRAVE__','â'=>'__A_CIRC__','ä'=>'__A_DIER__',
            'ù'=>'__U_GRAVE__','û'=>'__U_CIRC__','ü'=>'__U_DIER__',
            'î'=>'__I_CIRC__','ï'=>'__I_DIER__',
            'ô'=>'__O_CIRC__','ö'=>'__O_DIER__',
            'ç'=>'__C_CED__',
        ];
        $text = strtr($text, $protect);
        $this->debug('AFTER STEP 9', $text, $debug);

        // translitère tout le reste (š→s, ñ→n, …) mais nos accents FR sont protégés
        if (class_exists(\Transliterator::class)) {
            $text = \Transliterator::create('Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC')->transliterate($text);
        } else {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_D);
            $text = preg_replace('/\p{Mn}+/u', '', $text);
            $text = strtr($text, ['ß'=>'ss','Þ'=>'Th','þ'=>'th','Đ'=>'D','đ'=>'d','Ł'=>'L','ł'=>'l','Ø'=>'O','ø'=>'o','Œ'=>'Oe','œ'=>'oe','Æ'=>'Ae','æ'=>'ae']);
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C);
        }

        // restaure les accents FR
        $text = strtr($text, array_flip($protect));

        // 10. Conserver uniquement les caractères autorisés
        $text = preg_replace('/[^\p{L}\p{M}\p{N}\'"(),.!?:;\-%\s]/u', '', $text);
        $this->debug('AFTER STEP 10', $text, $debug);

        // 11. Simplifier les espaces
        $text = preg_replace('/\s+/', ' ', $text);
        $this->debug('AFTER STEP 11', $text, $debug);

        return trim($text);
    }
}
