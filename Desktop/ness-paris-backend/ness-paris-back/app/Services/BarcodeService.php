<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Gender;
use App\Models\Color;
use App\Models\Size;

class BarcodeService
{
    /**
     * Génère un code du type ENF-B01-S-001
     *
     * - ENF = 3 lettres du genre (gender->code)
     * - B01 = lettre couleur (première lettre du nom) + indice de rareté pour cette lettre
     *         ex : B01, B02, A01...
     * - S   = code taille (XS/S/M/L/XL/2-14, basé sur size->code ou size->label)
     * - 001 = séquence pour cette combinaison (genre + couleur codée + taille)
     */
    public function generate(Gender $gender, Color $color, Size $size): string
    {
        // 1. Préfixe genre
        $prefix = strtoupper(substr($gender->code, 0, 3)); // ENF, HOM, FEM, UNI...

        // 2. Code couleur basé sur 1ère lettre + ordre de rareté pour cette lettre
        //    On prend une "source" textuelle de la couleur : display_name, key ou slug
        $colorName = $color->display_name ?? $color->key ?? $color->slug ?? 'X';
        $firstLetter = strtoupper(substr(trim($colorName), 0, 1)); // B pour Black, W pour White, etc.

        // 2a. On compte combien de couleurs existent déjà avec cette 1ère lettre
        //     pour leur attribuer un indice de rareté (01, 02, 03...)
        //     Ici, on suppose que plus l'id est petit, plus la couleur est "ancienne"
        //     donc rareté = ordre d'apparition pour cette lettre.
        $colorIndexForLetter = Color::where(function ($q) use ($colorName) {
                $q->where('display_name', $colorName)
                  ->orWhere('key', $colorName)
                  ->orWhere('slug', $colorName);
            })
            ->value('id');

        // Si on n'a rien trouvé par nom exact, on calcule l'indice par la lettre
        if (! $colorIndexForLetter) {
            $colorIndexForLetter = Color::where(function ($q) {
                    // toutes les couleurs
                })
                ->whereRaw('UPPER(LEFT(COALESCE(display_name, key, slug), 1)) = ?', [$firstLetter])
                ->where('id', '<=', $color->id)
                ->count();
        }

        // On normalise en 2 chiffres
        $colorRarityIndex = max(1, (int) $colorIndexForLetter);
        $colorCode = sprintf('%s%02d', $firstLetter, $colorRarityIndex); // B01, B02, A01...

        // 3. Code taille (XS/S/M/L/XL/2-14)
        $sizeCode = $size->code ?? $size->label ?? (string) $size->id;
        $sizeCode = strtoupper($sizeCode); // S, M, L, 2-14, etc.

        // 4. Base = ENF-B01-S-
        $base = sprintf('%s-%s-%s-', $prefix, $colorCode, $sizeCode);

        // 5. Chercher le dernier code existant avec cette base
        $last = Product::where('reference_code', 'like', $base . '%')
            ->orderBy('reference_code', 'desc')
            ->first();

        $nextSeq = 1;

        if ($last) {
            $parts = explode('-', $last->reference_code);
            $seqStr = $parts[3] ?? null; // ENF-B01-S-001 → '001'

            if ($seqStr && ctype_digit($seqStr)) {
                $nextSeq = (int) $seqStr + 1;
            }
        }

        // 6. Retour final : ENF-B01-S-001
        return sprintf('%s%03d', $base, $nextSeq);
    }
}
