# Prompt 05 — Reprise de la génération avec Claude

## Contexte
Après le prompt 01 et le module auth générés avec GPT 5.2, la génération des modules suivants a atteint
la limite d'usage du compte ChatGPT : le ZIP produit n'a pas pu être téléchargé. La génération a été
reprise avec Claude (Anthropic), à partir du dépôt existant.

## Prompt utilisé
Mêmes consignes que `01-developpeur.md` et `03-testeur.md`, avec en entrée le dépôt GitHub
(spec.md, stride.md, definition-of-done.md, module auth existant) et la décision humaine sur les faux comptes :

> Reprends le dépôt existant sans réécrire le module auth. Ajoute la mitigation "faux comptes"
> (vérification email 24 h à usage unique, rate limiting de /api/register, mise sur invitation seulement),
> puis les modules paris, invitations, mises, paiement/séquestre, résultat/distribution et historique,
> avec le journal d'audit append-only et un test par abuse story. Respecte strictement docs/spec.md :
> aucune dépendance non listée, montants en centimes, contrôles côté serveur, refus par défaut.

## Point de vigilance (cours)
L'étude AppSec Santa 2026 classe Claude Opus 4.6 dans le groupe au taux de CVE le plus élevé (jusqu'à 29,9 %).
Le changement de modèle renforce donc la nécessité de la revue humaine (Security Champion) et de la CI.
