# File Tree: PA

**Generated:** 3/14/2026, 11:42:16 AM

```
├── PA - API
│   ├── app
│   │   ├── annonce.go
│   │   ├── ban.go
│   │   ├── bankingDetails.go
│   │   ├── category.go
│   │   ├── conteneur.go
│   │   ├── deposit.go
│   │   ├── facteur.go
│   │   ├── forum.go
│   │   ├── image.go
│   │   ├── metrics.go
│   │   ├── moderation.go
│   │   ├── notifications.go
│   │   ├── order.go
│   │   ├── paymentRequests.go
│   │   ├── payout.go
│   │   ├── planning.go
│   │   ├── poll.go
│   │   ├── projects.go
│   │   ├── refund.go
│   │   ├── service.go
│   │   ├── subscription.go
│   │   ├── tips.go
│   │   ├── twofa.go
│   │   ├── typePrestation.go
│   │   ├── user.go
│   │   └── zoom.go
│   ├── data
│   │   ├── badwords-google.json
│   │   ├── badwords.json
│   │   └── custom_sources.json
│   ├── db
│   │   ├── annonceRepository.go
│   │   ├── banRepository.go
│   │   ├── bankingDetailsRepository.go
│   │   ├── categoryRepository.go
│   │   ├── conteneurRepository.go
│   │   ├── db.go
│   │   ├── depositRepository.go
│   │   ├── facteurRepository.go
│   │   ├── forumRepository.go
│   │   ├── imageRepository.go
│   │   ├── notificationRepository.go
│   │   ├── orderRepository.go
│   │   ├── paymentRequestsRepository.go
│   │   ├── payoutRepository.go
│   │   ├── planningRepository.go
│   │   ├── pollRepository.go
│   │   ├── projectRepository.go
│   │   ├── refundRepository.go
│   │   ├── serviceRepository.go
│   │   ├── tipsRepository.go
│   │   ├── typePrestationRepository.go
│   │   └── userRepository.go
│   ├── models
│   │   ├── affectedEmployees.go
│   │   ├── annonce.go
│   │   ├── badge.go
│   │   ├── ban.go
│   │   ├── bankingDetails.go
│   │   ├── category.go
│   │   ├── conteneur.go
│   │   ├── deposit.go
│   │   ├── discussion.go
│   │   ├── endpoint.go
│   │   ├── facteur.go
│   │   ├── forum.go
│   │   ├── forumPost.go
│   │   ├── groupDiscussionMember.go
│   │   ├── groupDiscussions.go
│   │   ├── image.go
│   │   ├── message.go
│   │   ├── notification.go
│   │   ├── order.go
│   │   ├── paymentRequests.go
│   │   ├── payout.go
│   │   ├── planning.go
│   │   ├── poll.go
│   │   ├── project.go
│   │   ├── projectComments.go
│   │   ├── projectLikes.go
│   │   ├── projectStep.go
│   │   ├── projectStepMaterials.go
│   │   ├── refund.go
│   │   ├── refundRequest.go
│   │   ├── reports.go
│   │   ├── service.go
│   │   ├── tag.go
│   │   ├── tips.go
│   │   ├── typePrestation.go
│   │   └── user.go
│   ├── utils
│   │   └── fetcher.go
│   ├── .env.example
│   ├── Dockerfile
│   ├── api.go
│   ├── go.mod
│   └── go.sum
├── PA - BO
│   ├── assets
│   │   ├── css
│   │   │   ├── about.css
│   │   │   ├── admin-categories.css
│   │   │   ├── admin.css
│   │   │   ├── auth-shell.css
│   │   │   ├── chat.css
│   │   │   ├── contact.css
│   │   │   ├── customers.css
│   │   │   ├── dark.css
│   │   │   ├── dashboard.css
│   │   │   ├── error-page.css
│   │   │   ├── forum.css
│   │   │   ├── header.css
│   │   │   ├── home.css
│   │   │   ├── moderation.css
│   │   │   ├── pro.css
│   │   │   └── style.css
│   │   ├── img
│   │   │   ├── brand
│   │   │   │   ├── UpcycleDiminutif.png
│   │   │   │   ├── UpcycleDiminutif2.png
│   │   │   │   ├── UpcyclePetiSignVersion.png
│   │   │   │   ├── UpcyclePetiSignVersion2.png
│   │   │   │   ├── YpcycleVersion.png
│   │   │   │   └── petisign.png
│   │   │   ├── defaults
│   │   │   │   ├── container.png
│   │   │   │   └── placeholder.png
│   │   │   ├── team
│   │   │   │   ├── antoine-maclair.jpg
│   │   │   │   ├── frederic-molas.jpg
│   │   │   │   ├── laink-terracid.png
│   │   │   │   ├── norman-thavaud.jpg
│   │   │   │   ├── pierre-chabrier.jpg
│   │   │   │   ├── ronnand-peuplus.jpg
│   │   │   │   └── sylvain-levy.jpg
│   │   │   └── web
│   │   │       └── hero.jpg
│   │   ├── js
│   │   │   ├── admin-annonces.js
│   │   │   ├── admin-categories.js
│   │   │   ├── admin-containers.js
│   │   │   ├── admin-dashboard.js
│   │   │   ├── admin-materials.js
│   │   │   ├── admin-prestations.js
│   │   │   ├── admin-services.js
│   │   │   ├── admin-users.js
│   │   │   ├── blob-images.js
│   │   │   ├── button.js
│   │   │   ├── carroussel.js
│   │   │   ├── chat.js
│   │   │   ├── dark.js
│   │   │   ├── dashboard.js
│   │   │   ├── forum-detail.js
│   │   │   ├── forums.js
│   │   │   ├── global-search.js
│   │   │   ├── login.js
│   │   │   ├── mfa-verify.js
│   │   │   ├── moderation.js
│   │   │   ├── offers-loader.js
│   │   │   ├── offers-modal.js
│   │   │   └── order.js
│   │   └── json
│   │       └── error-mapping.json
│   ├── config
│   │   ├── db.php
│   │   ├── oauth-facebook.php
│   │   ├── oauth-google.php
│   │   └── stripe.php
│   ├── includes
│   │   ├── admin-header.php
│   │   ├── auth.php
│   │   ├── footer.php
│   │   ├── header.php
│   │   └── internal-api.php
│   ├── pages
│   │   ├── admin
│   │   │   ├── .htaccess
│   │   │   ├── annonce-delete-api.php
│   │   │   ├── annonce-update-status-api.php
│   │   │   ├── annonces-list-api.php
│   │   │   ├── annonces.php
│   │   │   ├── categories-list-api.php
│   │   │   ├── categories.php
│   │   │   ├── category-create-api.php
│   │   │   ├── category-delete-api.php
│   │   │   ├── category-update-api.php
│   │   │   ├── container-create-api.php
│   │   │   ├── container-delete-api.php
│   │   │   ├── container-update-api.php
│   │   │   ├── containers-api.php
│   │   │   ├── containers.php
│   │   │   ├── create-user-api.php
│   │   │   ├── dashboard-api.php
│   │   │   ├── dashboard.php
│   │   │   ├── gemini-material-api.php
│   │   │   ├── global-search-api.php
│   │   │   ├── logout.php
│   │   │   ├── material-create-api.php
│   │   │   ├── material-delete-api.php
│   │   │   ├── material-update-api.php
│   │   │   ├── materials-list-api.php
│   │   │   ├── materials.php
│   │   │   ├── moderation.php
│   │   │   ├── offers.php
│   │   │   ├── prestations.php
│   │   │   ├── reports.php
│   │   │   ├── requests.php
│   │   │   ├── search.php
│   │   │   ├── service-affected-add-api.php
│   │   │   ├── service-affected-list-api.php
│   │   │   ├── service-affected-remove-api.php
│   │   │   ├── service-create-api.php
│   │   │   ├── service-dashboard-api.php
│   │   │   ├── service-delete-api.php
│   │   │   ├── service-update-api.php
│   │   │   ├── services-list-api.php
│   │   │   ├── services.php
│   │   │   ├── type-prestation-create-api.php
│   │   │   ├── type-prestation-delete-api.php
│   │   │   ├── type-prestation-update-api.php
│   │   │   ├── type-prestations-list-api.php
│   │   │   ├── user-ban-api.php
│   │   │   ├── user-bans-api.php
│   │   │   ├── user-delete-api.php
│   │   │   ├── user-get-api.php
│   │   │   ├── user-update-api.php
│   │   │   ├── users-list-api.php
│   │   │   └── users.php
│   │   ├── common
│   │   │   ├── .htaccess
│   │   │   ├── chat.php
│   │   │   ├── create-annonce.php
│   │   │   ├── create-forum.php
│   │   │   ├── create-image.php
│   │   │   ├── create-payment-intent.php
│   │   │   ├── facteurs-api.php
│   │   │   ├── forum.php
│   │   │   ├── forums-api.php
│   │   │   ├── forums.php
│   │   │   ├── gemini-material-api.php
│   │   │   ├── offer.php
│   │   │   ├── offers-api.php
│   │   │   ├── offers.php
│   │   │   ├── order-cancel.php
│   │   │   ├── order-success.php
│   │   │   ├── order.php
│   │   │   ├── process-order.php
│   │   │   ├── upcycling-score.php
│   │   │   ├── user.php
│   │   │   ├── users-api.php
│   │   │   └── verify-payment.php
│   │   └── public
│   │       ├── .htaccess
│   │       ├── error.php
│   │       ├── login.php
│   │       └── mfa-verify.php
│   ├── .env.example
│   ├── .htaccess
│   ├── 403.php
│   ├── 404.php
│   └── composer.json
├── PA - Site Principal
│   ├── assets
│   │   ├── app
│   │   ├── css
│   │   │   ├── app
│   │   │   ├── about.css
│   │   │   ├── admin.css
│   │   │   ├── cgu.css
│   │   │   ├── chat.css
│   │   │   ├── configure.css
│   │   │   ├── contact.css
│   │   │   ├── customers.css
│   │   │   ├── dark.css
│   │   │   ├── deposits.css
│   │   │   ├── downloads.css
│   │   │   ├── error-page.css
│   │   │   ├── forum.css
│   │   │   ├── header.css
│   │   │   ├── home.css
│   │   │   ├── planning.css
│   │   │   ├── pro.css
│   │   │   ├── profile-badges.css
│   │   │   ├── style.css
│   │   │   ├── subscription.css
│   │   │   ├── support.css
│   │   │   └── updoc.css
│   │   ├── img
│   │   │   ├── brand
│   │   │   │   ├── UpcycleDiminutif.png
│   │   │   │   ├── UpcycleDiminutif2.png
│   │   │   │   ├── UpcyclePetiSignVersion.png
│   │   │   │   ├── UpcyclePetiSignVersion2.png
│   │   │   │   ├── YpcycleVersion.png
│   │   │   │   └── petisign.png
│   │   │   ├── defaults
│   │   │   │   ├── container.png
│   │   │   │   └── placeholder.png
│   │   │   ├── team
│   │   │   │   ├── LouisLucienIvanDetraux.jpg
│   │   │   │   ├── antoine-maclair.jpg
│   │   │   │   ├── frederic-molas.jpg
│   │   │   │   ├── laink-terracid.png
│   │   │   │   ├── norman-thavaud.jpg
│   │   │   │   ├── pierre-chabrier.jpg
│   │   │   │   ├── ronnand-peuplus.jpg
│   │   │   │   └── sylvain-levy.jpg
│   │   │   └── web
│   │   │       └── hero.jpg
│   │   ├── js
│   │   │   ├── about-map.js
│   │   │   ├── blob-images.js
│   │   │   ├── button.js
│   │   │   ├── carroussel.js
│   │   │   ├── chat.js
│   │   │   ├── configure.js
│   │   │   ├── containers-loader.js
│   │   │   ├── dark.js
│   │   │   ├── dashboard-premium.js
│   │   │   ├── dashboard.js
│   │   │   ├── deposits.js
│   │   │   ├── downloads.js
│   │   │   ├── easter-egg.js
│   │   │   ├── forum-detail.js
│   │   │   ├── forums.js
│   │   │   ├── login.js
│   │   │   ├── notifications-poll.js
│   │   │   ├── notifications.js
│   │   │   ├── offer-slider.js
│   │   │   ├── offers-loader.js
│   │   │   ├── offers-modal.js
│   │   │   ├── order.js
│   │   │   ├── planning.js
│   │   │   ├── pro-profile.js
│   │   │   ├── profile-badges.js
│   │   │   ├── profile-projects.js
│   │   │   ├── profile-sections.js
│   │   │   ├── profile.js
│   │   │   ├── register.js
│   │   │   ├── services-loader.js
│   │   │   ├── subscription-success.js
│   │   │   ├── subscription.js
│   │   │   ├── support.js
│   │   │   ├── tips-loader.js
│   │   │   ├── toast.js
│   │   │   ├── updoc-view.js
│   │   │   └── updoc.js
│   │   └── json
│   │       └── error-mapping.json
│   ├── config
│   │   ├── db.php
│   │   ├── glpi.php
│   │   ├── oauth-facebook.php
│   │   ├── oauth-google.php
│   │   └── stripe.php
│   ├── files
│   ├── includes
│   │   ├── auth.php
│   │   ├── ban-header.php
│   │   ├── customers-header.php
│   │   ├── footer.php
│   │   ├── header.php
│   │   ├── insee-proxy.php
│   │   ├── internal-api.php
│   │   ├── partials-header.php
│   │   ├── premium.php
│   │   └── pro-header.php
│   ├── pages
│   │   ├── common
│   │   │   ├── .htaccess
│   │   │   ├── categories-list-api.php
│   │   │   ├── chat.php
│   │   │   ├── create-annonce.php
│   │   │   ├── create-forum.php
│   │   │   ├── create-image.php
│   │   │   ├── create-payment-intent.php
│   │   │   ├── facteurs-api.php
│   │   │   ├── forum.php
│   │   │   ├── forums-api.php
│   │   │   ├── forums.php
│   │   │   ├── gemini-material-api.php
│   │   │   ├── moderator.php
│   │   │   ├── offer.php
│   │   │   ├── offers-api.php
│   │   │   ├── offers.php
│   │   │   ├── order-cancel.php
│   │   │   ├── order-success.php
│   │   │   ├── order.php
│   │   │   ├── pay-with-balance.php
│   │   │   ├── planning-api.php
│   │   │   ├── planning.php
│   │   │   ├── process-order.php
│   │   │   ├── support.php
│   │   │   ├── upcycling-score.php
│   │   │   ├── updoc.php
│   │   │   ├── user.php
│   │   │   ├── users-api.php
│   │   │   └── verify-payment.php
│   │   ├── customers
│   │   │   ├── .htaccess
│   │   │   ├── create-annonce.php
│   │   │   ├── create-deposit.php
│   │   │   ├── create-image.php
│   │   │   ├── create-payment-intent.php
│   │   │   ├── deposits-api.php
│   │   │   ├── deposits-detail-api.php
│   │   │   ├── deposits.php
│   │   │   ├── export-pdf.php
│   │   │   ├── facteurs-api.php
│   │   │   ├── gemini-api.php
│   │   │   ├── get-user-address.php
│   │   │   ├── logout.php
│   │   │   ├── notifications-poll.php
│   │   │   ├── notifications-read-all.php
│   │   │   ├── notifications-read.php
│   │   │   ├── notifications.php
│   │   │   ├── offer.php
│   │   │   ├── offers-api.php
│   │   │   ├── offers.php
│   │   │   ├── order-cancel.php
│   │   │   ├── order-success.php
│   │   │   ├── order.php
│   │   │   ├── planning-api.php
│   │   │   ├── planning.php
│   │   │   ├── process-order.php
│   │   │   ├── profile-order-api.php
│   │   │   ├── profile-section-api.php
│   │   │   ├── profile.php
│   │   │   ├── service.php
│   │   │   ├── services-api.php
│   │   │   ├── services.php
│   │   │   ├── tips-api.php
│   │   │   ├── tips.php
│   │   │   ├── type-prestations-list-api.php
│   │   │   ├── update-profile-api.php
│   │   │   ├── updoc-api.php
│   │   │   ├── updoc-view.php
│   │   │   ├── updoc.php
│   │   │   └── verify-payment.php
│   │   ├── partials
│   │   │   ├── .htaccess
│   │   │   ├── planning.php
│   │   │   └── profile.php
│   │   ├── pro
│   │   │   ├── .htaccess
│   │   │   ├── containers-api.php
│   │   │   ├── containers.php
│   │   │   ├── create-billing-portal.php
│   │   │   ├── create-subscription-checkout.php
│   │   │   ├── dashboard-free-api.php
│   │   │   ├── dashboard-premium-api.php
│   │   │   ├── dashboard-premium.php
│   │   │   ├── dashboard.php
│   │   │   ├── downloads.php
│   │   │   ├── logout.php
│   │   │   ├── notifications-poll.php
│   │   │   ├── notifications-read-all.php
│   │   │   ├── notifications-read.php
│   │   │   ├── notifications.php
│   │   │   ├── profile.php
│   │   │   ├── subscription-api.php
│   │   │   ├── subscription-intent-api.php
│   │   │   ├── subscription-success.php
│   │   │   ├── subscription.php
│   │   │   └── update-profile-api.php
│   │   └── public
│   │       ├── about.php
│   │       ├── ban.php
│   │       ├── cgu.php
│   │       ├── configure.php
│   │       ├── contact.php
│   │       ├── error.php
│   │       ├── index.php
│   │       ├── login.php
│   │       ├── logout.php
│   │       ├── map.php
│   │       ├── mfa-verify.php
│   │       ├── oauth-callback-facebook.php
│   │       ├── oauth-callback-google.php
│   │       ├── oauth-facebook.php
│   │       ├── oauth-google.php
│   │       ├── register.php
│   │       └── stripe-subscription-webhook.php
│   ├── scripts
│   │   └── pdf-generator.js
│   ├── .dockerignore
│   ├── .env.example
│   ├── .htaccess
│   ├── 403.php
│   ├── 404.php
│   ├── composer.json
│   ├── composer.phar
│   ├── package-lock.json
│   └── package.json
├── docs
│   ├── API.md
│   ├── ARCHITECTURE_TECHNIQUE.md
│   ├── AUTHENTICATION.md
│   ├── CONTRIBUTING.md
│   ├── DATABASE.md
│   ├── DEPLOYMENT.md
│   ├── OAUTH_SETUP.md
│   ├── SETUP.md
│   ├── STRIPE_SETUP.md
│   └── TOTP_SETUP.md
├── files
│   ├── badges
│   │   ├── ambassadeur.png
│   │   ├── eco_responsable.png
│   │   ├── expert.png
│   │   └── pionnier.png
│   ├── common
│   └── orders
├── .gitignore
├── README.md
├── composer.json
├── composer.phar
├── db_schema.sql
└── docker-compose.yml
```

---

_Generated by FileTree Pro Extension_
