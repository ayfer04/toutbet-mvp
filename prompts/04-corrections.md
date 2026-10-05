# Journal des corrections et décisions

| # | Erreur / problème rencontré | Prompt envoyé / action | Résultat |
|---|---|---|---|
| 1 | GPT 5.2 demande `definition-of-done.md`, référencé dans le prompt mais non fourni | Envoi du contenu de `definition-of-done.md` et `stride.md` | Contexte complet, module auth généré |
| 2 | Abuse story "faux comptes" sans mitigation dans la spec ; le modèle refuse d'inventer | Décision humaine : vérification email + rate limiting inscription + mise sur invitation | Spec mise à jour (section "Faux comptes") |
| 3 | Limite d'usage du compte ChatGPT : ZIP des modules paris/invitations/mises non téléchargeable | Reprise avec Claude (voir `05-generation-claude.md`) | Modules générés et intégrés |
| 4 | Le module auth généré ne chargeait pas les fichiers `.env` et le `.gitignore` n'excluait pas `.env` (risque de fuite de secrets) | Ajout de `symfony/dotenv`, `tests/bootstrap.php`, `/.env` dans `.gitignore` | Corrigé |
| 5 | Les tests existants partageaient la même IP : les rate limiters les auraient fait échouer entre eux | IP aléatoire par test | Corrigé |
| 6 | Pas de point d'entrée d'authentification : une requête sans jeton pouvait produire une erreur 500 au lieu de 401 | `JwtAuthenticator` implémente `start()` (401 JSON) | Corrigé |
| 7 | Composer (Packagist) inaccessible depuis l'environnement de génération : PHPUnit non exécuté | Syntaxe PHP vérifiée, migrations testées sur PostgreSQL 16 réel ; tests délégués à la CI GitHub Actions | À vérifier dans l'onglet Actions |
| 8 | CI : `composer install` échoue car `symfony/runtime` est un plugin Composer non autorisé (et inutilisé par le code) | Suppression de la dépendance, `allow-plugins` vide (aucun plugin tiers exécuté) | Correction de la chaîne d'approvisionnement |
| 9 | CI : 29 tests sur 36 échouent ("Could not find the entity manager for class App\\Entity\\User") : la config Doctrine du module auth généré ne déclarait pas `src/Entity`, et sans stratégie de nommage les colonnes ne correspondaient pas aux migrations | Ajout du mapping `App` et de `naming_strategy: underscore_number_aware` | Défaut présent depuis le 1er module : le code généré n'avait jamais été exécuté |
| 10 | CI : 2 tests auth générés appelaient `/api/protected-placeholder`, une route inexistante (404 au lieu de 401) : le test ne vérifiait donc rien | Tests redirigés vers une vraie route protégée (`/api/me/wagers`) | Un test qui passe pour une mauvaise raison est aussi dangereux qu'un test absent |
| 11 | CI : PHPStan (niveau 6) remonte 16 remarques de qualité, aucune de sécurité : types de tableaux non précisés, types de retour trop larges, propriétés remplies par Doctrine non détectées | Types précisés ; `phpstan.neon` ignore uniquement les 2 règles liées à Doctrine dans `src/Entity` (l'extension phpstan-doctrine n'est pas dans la stack autorisée) | Exclusion justifiée et limitée, tracée ici pour l'audit |
