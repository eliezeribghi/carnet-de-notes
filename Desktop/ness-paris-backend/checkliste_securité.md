Voici ta checklist **complète et précise**, dans l'ordre logique d'un débutant qui lance un vrai site e‑commerce pro en Laravel + SvelteKit.

***

## 🗄️ Base de données

### Structure
- [ ] Une seule DB (produits + stock + commandes + clients)
- [ ] Table `products` + `variants` + `stocks` (avec `variant_id` + `qty`)
- [ ] Table `orders` : `id`, `number`, `status`, `currency`, `subtotal`, `shipping`, `total`, `customer_email`, `created_at`
- [ ] Table `order_lines` : `order_id`, `variant_id`, `name_snapshot`, `size_snapshot`, `unit_price`, `qty`, `line_total`
- [ ] Champs Stripe sur `orders` : `stripe_checkout_session_id`, `stripe_payment_intent_id`, `paid_at`, `refund_status`, `refunded_amount`
- [ ] Table `customers` (B2B) : `company_name`, `siret` (optionnel), `contact_name`, `email`, `phone`, `billing_address`, `shipping_address`
- [ ] Table `return_requests` : `order_id`, `reason`, `status` (requested/approved/received/refunded), `created_at`

### Sécurité DB
- [ ] DB non exposée sur Internet (accès réseau privé uniquement)
- [ ] Deux users DB : `storefront_user` (lecture + commandes), `admin_user` (droits complets)
- [ ] Backups automatiques chiffrés
- [ ] Indexation sur `variant_id`, `order_id`, `email`, `status`

***

## 🔐 Sécurité applicative (Laravel)

- [ ] `.env` jamais commité sur Git (`.gitignore`)
- [ ] Clés Stripe `STRIPE_SECRET` + `STRIPE_WEBHOOK_SECRET` dans `.env` uniquement
- [ ] HTTPS obligatoire (certificat SSL, redirect HTTP → HTTPS)
- [ ] Hash des mots de passe via `bcrypt` (Laravel le fait par défaut)
- [ ] Auth backoffice avec rôles (admin ne voit pas ce que le client voit)
- [ ] Laravel Gates/Policies : un client ne voit que **ses** commandes
- [ ] Rate limiting sur les routes publiques (`/checkout`, `/api/*`)
- [ ] Protection CSRF sur tous les formulaires
- [ ] Logs d'erreurs activés (pas d'erreurs visibles en prod : `APP_DEBUG=false`)

***

## 💳 Stripe / Paiement

### Compte Stripe
- [ ] Créer compte Stripe + vérifier identité business
- [ ] Activer Apple Pay dans Dashboard Stripe (vérifier domaine)
- [ ] Activer Google Pay (même process que Apple Pay)
- [ ] Récupérer clé `sk_live_...` (secret) et `pk_live_...` (public)

### Intégration Laravel
- [ ] `composer require stripe/stripe-php`
- [ ] Config `config/services.php` : `stripe.secret` + `stripe.webhook_secret`
- [ ] Endpoint `POST /checkout/create-order` → crée `order` + `order_lines` en `pending_payment` (recalcule les prix **côté serveur**)
- [ ] Endpoint `POST /checkout/stripe/session` → crée Checkout Session Stripe avec `line_items`, `metadata.order_id`, `success_url`, `cancel_url`, `customer_email`
- [ ] Retourner `session.url` au frontend → redirect
- [ ] Page `/checkout/success` : affiche "Merci" seulement (ne marque **pas** `paid` ici, c'est le webhook qui le fait)
- [ ] Page `/cart` : lien "Annuler" → `cancel_url`

### Webhook (source de vérité)
- [ ] Endpoint public `POST /stripe/webhook` (exclu du CSRF middleware)
- [ ] Vérifier signature Stripe (`Webhook::constructEvent`)
- [ ] Écouter `checkout.session.completed`
- [ ] Vérifier `payment_status === 'paid'`
- [ ] Récupérer `order_id` depuis `metadata`
- [ ] Idempotence : ne traiter que si `order.status !== 'paid'`
- [ ] Dans une **transaction DB avec `lockForUpdate`** : vérifier stock dispo → décrémenter → passer `status = paid` → enregistrer `payment_intent_id` + `paid_at`
- [ ] Envoyer email confirmation au client (Laravel Mail + queue)
- [ ] Configurer le webhook dans Dashboard Stripe (URL + events)
- [ ] Tester en local avec Stripe CLI : `stripe listen --forward-to localhost/stripe/webhook`

***

## 📦 Stock temps réel (anti-oversell)

- [ ] Lecture stock depuis la même DB que le backoffice (une source de vérité)
- [ ] Jamais de décrémentation au panier, uniquement au webhook `paid`
- [ ] Transaction avec `lockForUpdate` lors du webhook :

```php
DB::transaction(function () use ($order) {
    foreach ($order->lines as $line) {
        $stock = Stock::where('variant_id', $line->variant_id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($stock->qty < $line->qty) {
            throw new \Exception('Stock insuffisant');
        }

        $stock->decrement('qty', $line->qty);
    }
    $order->update(['status' => 'paid', 'paid_at' => now()]);
});
```
- [ ] Si stock insuffisant au moment du paiement : rembourser immédiatement via Stripe Refund + email client [faun](https://faun.pub/understanding-lockforupdate-and-sharedlock-in-laravel-d31d45c5138e)

***

## 📧 Emails transactionnels

- [ ] Email **confirmation de commande** (récap lignes, total, adresse, numéro de commande)
- [ ] Email **expédition** (numéro de suivi transporteur)
- [ ] Email **retour/remboursement accepté**
- [ ] Email **compte créé** (si tu fais des comptes clients)
- [ ] Utiliser une queue (`QUEUE_CONNECTION=database` ou Redis) pour ne pas bloquer la requête
- [ ] Configurer SMTP pro (ex : Resend, Mailgun, Postmark) → ne pas utiliser Gmail

***

## 🔄 Retours & remboursements

- [ ] Page "Politique de retours & remboursements" visible avant achat (obligatoire en B2C, bonne pratique B2B) [victorisavocat](https://www.victorisavocat.com/blog/droit-retractation-guide-e-commercants)
- [ ] Formulaire "Demander un retour" : numéro de commande + email + motif
- [ ] Table `return_requests` avec statuts
- [ ] Endpoint admin pour approuver/refuser
- [ ] Remboursement Stripe via `$stripe->refunds->create(['payment_intent' => ..., 'amount' => ...])` (partiel ou total) [docs.stripe](https://docs.stripe.com/refunds)
- [ ] Email client à chaque changement de statut retour

***

## 🗃️ Conservation des données (RGPD/CNIL)

- [ ] **Jamais** stocker numéro CB, CVC, données Apple Pay (Stripe gère tout) [stripe](https://stripe.com/fr/resources/more/how-to-store-customer-card-information-a-quick-guide-to-staying-compliant)
- [ ] Stocker uniquement les données nécessaires à la commande (minimisation) [cnil](https://www.cnil.fr/sites/cnil/files/atoms/files/referentiel_traitements-donnees-caractere-personnel_gestion-activites-commerciales.pdf)
- [ ] Durée de conservation commandes/facturation : **10 ans** (obligation comptable) [cnil](https://www.cnil.fr/sites/cnil/files/atoms/files/guide_durees_de_conservation.pdf)
- [ ] Durée compte client inactif : définir une politique (ex : anonymisation après 3 ans sans commande) [cnil](https://www.cnil.fr/fr/passer-laction/les-durees-de-conservation-des-donnees)
- [ ] Page **Politique de confidentialité** (mentions RGPD obligatoires : qui collecte, pourquoi, durée, droits) [cnil](https://www.cnil.fr/fr/rgpd-en-pratique-maitrisez-votre-relation-client)
- [ ] Formulaire de contact pour exercer les droits (accès, rectification, suppression) [entreprendre.service-public.gouv](https://entreprendre.service-public.gouv.fr/vosdroits/F24270)

***

## 🏗️ Infrastructure & déploiement

- [ ] Environnements séparés : `local` / `staging` / `production`
- [ ] Ne jamais copier la DB prod en staging sans anonymiser les données
- [ ] Variables d'env séparées par environnement
- [ ] Mode prod : `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `php artisan config:cache` + `route:cache` + `view:cache` en prod
- [ ] Queue worker actif en prod (Supervisor ou équivalent)
- [ ] Monitoring erreurs (ex : Sentry, Flare)
- [ ] Alertes si webhook Stripe en échec

***

## 📋 Pages légales obligatoires (e-commerce France)

- [ ] CGV (Conditions Générales de Vente)
- [ ] Politique de confidentialité (RGPD)
- [ ] Politique de retours / rétractation
- [ ] Mentions légales (identité du vendeur, SIRET, etc.)
- [ ] Politique cookies (si tu utilises Google Analytics, etc.)

***

## ✅ Avant le go-live

- [ ] Tester tout le flow en mode **Stripe test** (carte `4242 4242 4242 4242`)
- [ ] Tester Apple Pay en staging
- [ ] Tester le webhook avec Stripe CLI
- [ ] Tester un remboursement complet + partiel
- [ ] Tester une commande avec stock à 0 → refus propre
- [ ] Vérifier que `APP_DEBUG=false` en prod
- [ ] Vérifier HTTPS partout
- [ ] Vérifier que l'email de confirmation part bien
- [ ] Passer les clés Stripe de `test` → `live`