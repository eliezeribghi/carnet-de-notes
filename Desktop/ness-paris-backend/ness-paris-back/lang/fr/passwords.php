<?php
// =============================================================================
// lang/fr/passwords.php
//
// Messages FR du broker Password (retournés en JSON par le trait via __($status)).
// Sans ce fichier, l'API renvoie les clés brutes ("passwords.sent", etc.).
// =============================================================================

return [
    'reset'     => 'Votre mot de passe a été réinitialisé.',
    'sent'      => 'Un email de réinitialisation vous a été envoyé.',
    'throttled' => 'Veuillez patienter avant de réessayer.',
    'token'     => 'Ce jeton de réinitialisation est invalide ou a expiré.',
    'user'      => "Aucun utilisateur ne correspond à cette adresse email.",
];