# Mise en place de l'authentification TOTP (2FA) dans un projet

Ce guide explique comment implémenter une authentification à deux facteurs (2FA) basée sur le protocole **TOTP** _(Time-based One-Time Password)_ dans un projet, en s'appuyant sur une stack **Go (API)** + **PHP/JS (front)**.

---

## Sommaire

1. [C'est quoi le TOTP ?](#cest-quoi-le-totp-)
2. [Applications compatibles](#applications-compatibles)
3. [Côté serveur - Go](#côté-serveur--go)
4. [Côté client - PHP / JS](#côté-client--php--js)
5. [Flux d'authentification complet](#flux-dauthentification-complet)
6. [Base de données](#base-de-données)
7. [Activer le 2FA côté utilisateur](#activer-le-2fa-côté-utilisateur)
8. [Se connecter avec le 2FA activé](#se-connecter-avec-le-2fa-activé)
9. [Désactiver le 2FA](#désactiver-le-2fa)
10. [Dépannage](#dépannage)

---

## C'est quoi le TOTP ?

Le **TOTP** est un standard ouvert (RFC 6238) qui génère un code à 6 chiffres valable **30 secondes**, calculé à partir :

- d'un **secret partagé** (généré une fois lors de l'activation),
- de l'**heure actuelle** (synchronisée entre le téléphone de l'utilisateur et le serveur).

Même si un attaquant vole le mot de passe, il ne peut pas se connecter sans le code du moment.

---

## Applications compatibles

L'utilisateur doit installer une application TOTP sur son téléphone. Toutes les apps suivantes supportent le standard `otpauth://` :

| Application             | Android | iOS | Remarque                                        |
| ----------------------- | ------- | --- | ----------------------------------------------- |
| Google Authenticator    | Oui     | Oui | Simple, pas de sauvegarde cloud                 |
| Authy                   | Oui     | Oui | Sauvegarde chiffrée dans le cloud               |
| Microsoft Authenticator | Oui     | Oui | Bon si écosystème Microsoft                     |
| 2FAS                    | Oui     | Oui | Open source, sauvegarde locale                  |
| Aegis                   | Oui     | Non | Open source, export chiffré, recommandé Android |
| Raivo                   | Non     | Oui | Open source, recommandé iOS                     |

---

## Côté serveur -- Go

### Dépendance

```bash
go get github.com/pquerna/otp
```

### Générer un secret et une URL OTP

```go
import "github.com/pquerna/otp/totp"

key, err := totp.Generate(totp.GenerateOpts{
    Issuer:      "MonApplication",   // Nom affiché dans l'app TOTP
    AccountName: user.Email,         // Identifiant de l'utilisateur
})
if err != nil {
    // gérer l'erreur
}

secret := key.Secret()  // à stocker en base après confirmation
otpURL := key.URL()     // à encoder en QR code côté front
```

L'`otpURL` a ce format :

```
otpauth://totp/MonApplication:user@email.com?secret=ABCDEF123456&issuer=MonApplication
```

### Valider un code TOTP

```go
valid := totp.Validate(codeEntréParLUtilisateur, secretStockéEnBase)
if !valid {
    // code incorrect ou expiré
}
```

### Endpoints recommandés

```go
router.GET("/users/{id}/2fa-info", handlers.Get2FAInfo)     // 2FA actif ?
router.POST("/users/{id}/2fa/setup", handlers.Setup2FA)     // Générer le secret
router.POST("/users/{id}/2fa/enable", handlers.Enable2FA)   // Activer (vérifie le code)
router.POST("/users/{id}/2fa/disable", handlers.Disable2FA) // Désactiver
router.POST("/2fa/verify", handlers.Verify2FA)              // Vérifier lors du login
```

### Handler Setup2FA (génération)

```go
func Setup2FA(w http.ResponseWriter, r *http.Request) {
    userID := getIDFromPath(r)

    user, err := db.GetUserByID(userID)
    if err != nil {
        sendError(w, "User not found", http.StatusNotFound)
        return
    }

    key, err := totp.Generate(totp.GenerateOpts{
        Issuer:      "MonApplication",
        AccountName: user.Email,
    })
    if err != nil {
        sendError(w, "Unable to generate 2FA secret", http.StatusInternalServerError)
        return
    }

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(map[string]string{
        "secret":  key.Secret(),
        "otp_url": key.URL(),
    })
    // Ne pas encore stocker le secret - seulement après Enable2FA
}
```

### Handler Enable2FA (activation avec vérification)

```go
func Enable2FA(w http.ResponseWriter, r *http.Request) {
    userID := getIDFromPath(r)

    var body struct {
        Secret string `json:"secret"`
        Code   string `json:"code"`
    }
    json.NewDecoder(r.Body).Decode(&body)

    if !totp.Validate(body.Code, body.Secret) {
        sendError(w, "Invalid OTP code", http.StatusUnauthorized)
        return
    }

    // Stocker le secret uniquement si le code est valide
    db.Enable2FA(userID, body.Secret)

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(map[string]bool{"success": true})
}
```

### Handler Verify2FA (connexion en deux temps)

Le login classique retourne un **token temporaire** si le 2FA est actif. Ce token sert uniquement à valider le code TOTP.

```go
// Lors du login classique, si 2FA actif :
tempToken := jwt.NewWithClaims(jwt.SigningMethodHS256, jwt.MapClaims{
    "user_id": user.ID,
    "type":    "mfa_pending",
    "exp":     time.Now().Add(5 * time.Minute).Unix(),
})

// Handler Verify2FA
func Verify2FA(w http.ResponseWriter, r *http.Request) {
    var body struct {
        TempToken string `json:"temp_token"`
        Code      string `json:"code"`
    }
    json.NewDecoder(r.Body).Decode(&body)

    claims := verifyJWT(body.TempToken) // doit avoir type == "mfa_pending"
    if claims == nil {
        sendError(w, "Invalid or expired temporary token", http.StatusUnauthorized)
        return
    }

    secret := db.Get2FASecret(claims["user_id"])

    if !totp.Validate(body.Code, secret) {
        sendError(w, "Invalid OTP code", http.StatusUnauthorized)
        return
    }

    fullToken := issueSessionJWT(claims["user_id"])

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(map[string]string{"token": fullToken})
}
```

---

## Côté client -- PHP / JS

### Générer et afficher le QR code

Appelle l'endpoint `/users/{id}/2fa/setup` pour obtenir l'`otp_url`, puis génère un QR code depuis cette URL.

**Option 1 - Bibliothèque PHP (endroid/qr-code)**

```bash
composer require endroid/qr-code
```

```php
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$otpUrl = $apiResponse['otp_url'];

$qrCode = QrCode::create($otpUrl)->setSize(200);
$writer = new PngWriter();
$result = $writer->write($qrCode);

echo '<img src="' . $result->getDataUri() . '" alt="QR Code 2FA">';
```

**Option 2 - Librairie JS côté navigateur**

```html
<script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>

<canvas id="qrcode"></canvas>

<script>
  fetch("/api/users/" + userId + "/2fa/setup", { method: "POST" })
    .then((r) => r.json())
    .then((data) => {
      QRCode.toCanvas(document.getElementById("qrcode"), data.otp_url);
      document.getElementById("totp-secret").value = data.secret;
    });
</script>
```

### Formulaire d'activation

```html
<form id="enable-2fa-form">
  <input type="hidden" id="totp-secret" value="" />
  <label>Code de vérification</label>
  <input
    type="text"
    id="totp-code"
    maxlength="6"
    placeholder="123456"
    autocomplete="one-time-code"
    inputmode="numeric"
  />
  <button type="submit">Activer le 2FA</button>
</form>

<script>
  document
    .getElementById("enable-2fa-form")
    .addEventListener("submit", function (e) {
      e.preventDefault();
      fetch("/api/users/" + userId + "/2fa/enable", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          secret: document.getElementById("totp-secret").value,
          code: document.getElementById("totp-code").value,
        }),
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.success) {
            // Rediriger ou afficher un message de confirmation
          }
        });
    });
</script>
```

### Formulaire de vérification lors de la connexion

```html
<form id="verify-2fa-form">
  <input
    type="hidden"
    id="temp-token"
    value="<?= htmlspecialchars($tempToken) ?>"
  />
  <label>Code depuis ton application</label>
  <input
    type="text"
    id="totp-code"
    maxlength="6"
    placeholder="123456"
    autocomplete="one-time-code"
    inputmode="numeric"
  />
  <button type="submit">Valider</button>
</form>

<script>
  document
    .getElementById("verify-2fa-form")
    .addEventListener("submit", function (e) {
      e.preventDefault();
      fetch("/api/2fa/verify", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          temp_token: document.getElementById("temp-token").value,
          code: document.getElementById("totp-code").value,
        }),
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.token) {
            localStorage.setItem("token", data.token);
            window.location.href = "/dashboard";
          }
        });
    });
</script>
```

---

## Flux d'authentification complet

```
Utilisateur              Front (PHP/JS)              API (Go)
     |                        |                          |
     |-- email + password ---->|                          |
     |                        |-- POST /login ----------->|
     |                        |                          | (2FA actif ?)
     |                        |<-- { requires_2fa: true, |
     |                        |     temp_token: "eyJ..." }|
     |                        |                          |
     |<-- Ecran de saisie -----|                          |
     |-- code 6 chiffres ----->|                          |
     |                        |-- POST /2fa/verify ------>|
     |                        |   { temp_token, code }   | totp.Validate()
     |                        |                          |
     |                        |<-- { user, token (JWT) } |
     |<-- Connecté ------------|                          |
```

---

## Base de données

Ajoute deux colonnes sur la table `users` :

```sql
ALTER TABLE users
    ADD COLUMN two_fa_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN two_fa_secret  VARCHAR(64) DEFAULT NULL;
```

Le secret n'est stocké qu'après confirmation réussie du premier code (dans `Enable2FA`). Avant cela, il est uniquement transmis en mémoire entre `setup` et `enable`.

> En production, chiffre la colonne `two_fa_secret` avec AES ou utilise un coffre de secrets (Vault, AWS KMS, etc.).

---

## Activer le 2FA côté utilisateur

1. L'utilisateur va dans les paramètres de son compte.
2. Il déclenche la configuration du 2FA (bouton ou lien dédié).
3. Le front appelle `POST /users/{id}/2fa/setup` et affiche le QR code retourné.
4. L'utilisateur scanne le QR code avec son application TOTP.
5. Il saisit le code à 6 chiffres affiché dans l'application.
6. Le front envoie `POST /users/{id}/2fa/enable` avec le secret et le code.
7. L'API valide le code et enregistre le secret en base.

---

## Se connecter avec le 2FA activé

1. Login classique (`POST /login`) avec email + mot de passe.
2. Si 2FA actif, l'API retourne `{ requires_2fa: true, temp_token: "..." }` au lieu du token complet.
3. Le front affiche un champ de saisie du code TOTP.
4. L'utilisateur ouvre son application et saisit le code affiché.
5. Le front envoie `POST /2fa/verify` avec le `temp_token` et le `code`.
6. L'API valide le code et retourne le vrai token de session JWT.

---

## Désactiver le 2FA

```
POST /users/{id}/2fa/disable
```

Met `two_fa_enabled = false` et efface `two_fa_secret` en base.

Si un utilisateur perd l'accès à son application, un administrateur peut désactiver manuellement :

```sql
UPDATE users
SET two_fa_enabled = FALSE, two_fa_secret = NULL
WHERE id = 'uuid-de-l-utilisateur';
```

---

---

## Dépannage

### "Invalid OTP code" alors que le code semble correct

- Vérifie que l'heure du serveur est synchronisée (NTP). Une dérive de plus de 30 secondes invalide tous les codes.
- Vérifie l'heure du téléphone : Paramètres > Date & Heure > Automatique.
- La librairie `pquerna/otp` tolère par défaut une fenêtre d'une période. Tu peux l'augmenter :

```go
valid, err := totp.ValidateCustom(code, secret, time.Now(), totp.ValidateOpts{
    Period:    30,
    Skew:      1, // tolère 1 période d'écart (±30 s)
    Digits:    otp.DigitsSix,
    Algorithm: otp.AlgorithmSHA1,
})
```

### "Invalid or expired temporary token"

Le token `mfa_pending` a une durée de vie courte. L'utilisateur doit recommencer la connexion depuis le début.

### QR code scanné mais aucun compte n'apparaît dans l'application

- Vérifie que l'`otp_url` est bien formée (`otpauth://totp/...`).
- Propose la saisie manuelle du secret en complément du QR code.
- Certaines applications n'acceptent pas les labels avec des caractères spéciaux : encode correctement `AccountName`.

### `totp.Validate` retourne toujours `false` alors que le secret est correct

Vérifie que le secret transmis lors de `enable` est exactement le même que celui retourné par `setup`. Ne régénère pas un nouveau secret entre les deux appels.
