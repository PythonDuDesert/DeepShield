# DeepShield
DeepShield - Détection de deepfakes audio/vidéo pour KYC

Lucas DUHOO
Omar TANARADJE
Olivier YAMMINE


<br><br>
ROLES : 0='admin'; 1='premium user'; 2='user'
<br>
---

INFORMATION SUR LES LOGS : 

'info' → normal<br>
'important_info' → action sensible<br>
'warning' → avertissement/comportement suspect<br>
'error' → problème technique/erreur critique<br>
'BDD' → contexte technique spécifique<br>

filtres disponibles

---

## Authentification
 
| Fonction | Type | Description |
|---|---|---|
| `log_login` | `info` | Connexion réussie d'un utilisateur |
| `log_login_failed` | `info` | Tentative de connexion échouée |
| `log_logout` | `info` | Déconnexion normale |
| `log_logout_timeout` | `info` | Déconnexion automatique (timeout) |
| `log_logout_unauthorized` | `warning` | Déconnexion forcée (accès non autorisé) |
 
---
 
## Utilisateurs
 
| Fonction | Type | Description |
|---|---|---|
| `log_profile_created` | `info` | Création d'un compte utilisateur |
| `log_profile_deleted` | `warning` | Suppression d'un profil utilisateur |
| `log_profile_updated` | `info` | Modification d'un profil utilisateur |
| `log_user_deleted` | `warning` | Suppression d'un utilisateur par un admin |
 
---
 
## Mots de passe
 
| Fonction | Type | Description |
|---|---|---|
| `log_password_changed` | `info` | Changement de mot de passe |
| `log_password_forgot` | `info` | Demande de réinitialisation de mot de passe |
 
---
 
## Administration / Gestion
 
| Fonction | Type | Description |
|---|---|---|
| `log_user_status_changed` | `important_info` | Activation / désactivation d'un utilisateur |
| `log_user_unblocked` | `important_info` | Déblocage manuel d'un utilisateur |
| `log_user_role_changed` | `important_info` | Modification du rôle d'un utilisateur |
 
---
 
## Sécurité
 
| Fonction | Type | Description |
|---|---|---|
| `log_ip_blocked` | `warning` | Blocage temporaire d'une IP (brute force) |
| `log_user_blocked` | `warning` | Blocage temporaire d'un compte |
| `log_user_auto_unblocked` | `warning` | Déblocage automatique après expiration |
 
---
 
## Analyse Vidéo
 
| Fonction | Type | Description |
|---|---|---|
| `log_video_analysed` | `info` | Analyse d'une vidéo avec score de deepfake |
 
---
 
## Base de données (BDD)
 
| Fonction | Type | Description |
|---|---|---|
| `log_BDD_backup` | `BDD` / `warning` | Action sur un backup (création, suppression, restauration…) |
| `log_BDD_backup_error` | `error` | Erreur lors d'un backup |
| `log_database_error` | `error` | Erreur liée à une opération base de données |
 
---
 
## Erreurs
 
| Fonction | Type | Description |
|---|---|---|
| `log_error` | `error` | Log d'erreur générique |
 
---
 
## Fonction principale
 
| Fonction | Type | Description |
|---|---|---|
| `write_log` | `dynamique` | Fonction centrale d'écriture des logs (avec hash et chaînage) |
 
---
 
## Intégrité
 
| Fonction | Type | Description |
|---|---|---|
| `get_last_log_hash` | `interne` | Récupère le hash du dernier log |
| `verify_logs_integrity` | `audit` | Vérifie l'intégrité complète de la chaîne de logs |
 
---
 
## Lecture et Analyse
 
| Fonction | Type | Description |
|---|---|---|
| `read_all_logs` | `lecture` | Retourne tous les logs (plus récents en premier) |
| `read_logs` | `lecture` | Retourne les logs avec filtres |
| `count_logs_by_type` | `stats` | Compte les logs par type |
| `get_recent_logs` | `lecture` | Retourne les derniers logs |
| `search_logs` | `recherche` | Recherche par mot-clé |
 
---
 
## Maintenance
 
| Fonction | Type | Description |
|---|---|---|
| `cleanup_old_logs` | `maintenance` | Supprime les logs anciens (> X jours) |

---

<br><br>
**Crédits icones : https://www.flaticon.com/fr/icones**
