# 🚀 CollabIDE - IDE Collaboratif Multi-Langages

**CollabIDE** est un environnement de développement intégré collaboratif en temps réel, construit avec des technologies web standards. Codez à plusieurs en simultané avec une stack simple et efficace.

## ✨ Fonctionnalités principales

- 👥 **Collaboration temps réel** - Édition simultanée du même code par plusieurs développeurs
- 💻 **Support multi-langage** - JavaScript, PHP, HTML, CSS, SQL et bien d'autres
- 🔌 **Architecture modulaire** - Fonctionnalités extensibles via plugins
- 🔄 **Synchronisation instantanée** - Changements visibles en temps réel pour tous les collaborateurs
- 📊 **Console partagée** - Exécution et débogage collaboratif
- 🔒 **Gestion de permissions** - Contrôle fin des accès (lecture/écriture)

## 🛠 Stack Technique

![PHP](https://img.shields.io/badge/PHP-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)
![WebSocket](https://img.shields.io/badge/WebSocket-010101?logo=websocket&logoColor=white)

**Backend:**
- PHP 8.x pour le traitement côté serveur
- MySQL/MariaDB pour le stockage des projets
- WebSocket pour la communication temps réel

**Frontend:**
- JavaScript vanilla (pas de framework)
- HTML5/CSS3 modernes
- Monaco Editor (moteur d'édition de VS Code)

## 🎯 Public Cible

- Écoles d'informatique
- Petites équipes de développement
- Enseignants/formateurs
- Étudiants en programmation
- Communautés d'apprentissage

## 🚀 Démarrage Rapide

### Prérequis
- PHP 8.0+
- MySQL 5.7+
- Node.js 16+ (pour les assets)

```bash
# 1. Cloner le dépôt
git clone https://github.com/coolternet/WebCollab-Editor.git
cd collabide

# 2. Configurer la base de données
mysql -u root -p < database/schema.sql

# 3. Installer les dépendances
composer install
npm install

# 4. Démarrer les services
php -S localhost:8000 -t public/
node websocket-server.js
```

**🌐 Démo Live**
Accédez à notre environnement de test : demo.collabide.example

**📄 Licence**

- Licence MIT.

**🤝 Comment Contribuer**
- Forkez le projet
- Créez votre branche (git checkout -b feature/ma-fonctionnalite)
- Committez vos changements (git commit -am 'Ajout d'une super fonctionnalité')
- Pushez (git push origin feature/ma-fonctionnalite)
- Ouvrez une Pull Request


💻 Développé par Alexounet & Yan Bourgeois | 📧 Contact: dev@evolution-network.ca
===
