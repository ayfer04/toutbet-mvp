# Modélisation STRIDE - ToutBet'

| Catégorie | Menace identifiée | Composant visé | Mitigation proposée |
|---|---|---|---|
| Spoofing | Un attaquant usurpe le compte d'un parieur afin d'accéder à ses paris et à ses fonds. | Application mobile / Serveur d'application | Authentification forte, gestion sécurisée des sessions |
| Spoofing | Un attaquant se fait passer pour un Bookie afin de gérer ou valider un pari. | Serveur d'application | Vérification de l'identité et du rôle de l'utilisateur |
| Tampering | Un parieur malveillant modifie sa mise après la clôture du pari. | Serveur d'application / Base de données | Vérification côté serveur et blocage des modifications après clôture |
| Tampering | Un attaquant modifie le résultat d'un pari afin de faire gagner un participant. | Serveur d'application / Base de données | Contrôle des droits, validation côté serveur et journalisation |
| Tampering | Un attaquant modifie les cotations ou les plafonds de mise d'un pari. | Serveur d'application / Base de données | Contrôle des permissions et validation des modifications |
| Repudiation | Un utilisateur nie avoir effectué un pari. | Logs / Base de données | Logs horodatés et signature du pari |
| Repudiation | Un Bookie nie avoir validé le résultat d'un pari. | Logs / Base de données | Journalisation des actions et conservation des traces |
| Information disclosure | Un attaquant récupère les données personnelles des utilisateurs. | Base de données / Serveur d'application | Chiffrement des données et contrôle d'accès |
| Information disclosure | Un attaquant récupère des données liées aux paiements des utilisateurs. | API de paiement / Serveur d'application | Chiffrement des échanges et limitation des données stockées par l'application |
| Information disclosure | Un utilisateur accède à l'historique ou aux informations d'un autre utilisateur. | Serveur d'application / Base de données | Contrôle d'accès et vérification des droits |
| Denial of service | Un attaquant envoie un grand nombre de requêtes afin de rendre l'application indisponible. | Serveur d'application / API | Protection contre les attaques et surveillance du trafic |
| Denial of service | Des bots envoient de nombreuses participations afin de surcharger un pari. | Serveur d'application | Limitation des requêtes et détection des bots |
| Elevation of privilege | Un parieur obtient les droits d'un Bookie afin de modifier ou valider un pari. | Serveur d'application / Gestion des rôles | Gestion des rôles et contrôle des permissions côté serveur |
| Elevation of privilege | Un utilisateur accède à des fonctionnalités qui ne sont pas disponibles pour son rôle. | API | Contrôle d'accès basé sur les rôles |