

### 1. **Navigation et gestion des fichiers**
Ces commandes sont utilisées pour explorer et manipuler les fichiers et répertoires dans Linux.

- `ls` : Liste les fichiers dans un répertoire.
  - `ls -l` : Affiche une liste détaillée avec les permissions, le propriétaire, et la taille.
  - `ls -a` : Affiche les fichiers cachés.
  - `ls -R` : Liste récursive, y compris les sous-dossiers.
  
- `cd <dossier>` : Change de répertoire.
  - `cd ..` : Remonte au répertoire parent.
  - `cd /` : Va à la racine du système de fichiers.

- `pwd` : Affiche le chemin complet du répertoire courant.

- `mkdir <dossier>` : Crée un répertoire.
  - `mkdir -p <dossier/dossier2>` : Crée les répertoires parents manquants.

- `rmdir <dossier>` : Supprime un répertoire vide.

- `rm <fichier>` : Supprime un fichier.
  - `rm -r <dossier>` : Supprime un répertoire et son contenu.
  - `rm -f <fichier>` : Forcer la suppression sans confirmation.

- `cp <source> <destination>` : Copie des fichiers ou dossiers.
  - `cp -r <dossier_source> <dossier_destination>` : Copie un répertoire récursivement.

- `mv <source> <destination>` : Déplace ou renomme des fichiers/dossiers.

- `touch <fichier>` : Crée un fichier vide ou met à jour la date de modification.

- `cat <fichier>` : Affiche le contenu d’un fichier.
  - `cat fichier1 fichier2` : Concatène plusieurs fichiers.
  
- `more <fichier>` ou `less <fichier>` : Affiche le contenu d’un fichier page par page.

- `head <fichier>` : Affiche les premières lignes d’un fichier.
  - `head -n 10 <fichier>` : Affiche les 10 premières lignes.

- `tail <fichier>` : Affiche les dernières lignes d’un fichier.
  - `tail -f <fichier>` : Suit en temps réel les nouvelles lignes ajoutées à un fichier.

### 2. **Recherche et filtre**
Ces commandes permettent de rechercher des fichiers et filtrer du contenu dans Linux.

- `find <chemin> -name <nom_fichier>` : Recherche des fichiers par nom.
  - `find /home -name "*.txt"` : Recherche tous les fichiers `.txt` dans `/home`.

- `grep <mot> <fichier>` : Recherche une chaîne spécifique dans un fichier.
  - `grep -r <mot> <dossier>` : Recherche récursive dans tous les fichiers d’un dossier.

- `locate <nom_fichier>` : Localise rapidement un fichier dans le système.

- `which <commande>` : Affiche le chemin complet d’une commande.
  
- `wc <fichier>` : Compte les lignes, mots et caractères dans un fichier.
  - `wc -l <fichier>` : Compte uniquement les lignes.

### 3. **Gestion des processus**
Ces commandes gèrent les processus actifs et les performances du système.

- `ps` : Liste les processus actifs.
  - `ps aux` : Affiche tous les processus en cours d'exécution.

- `top` ou `htop` : Affiche les processus actifs en temps réel avec leur utilisation des ressources.

- `kill <pid>` : Termine un processus en fonction de son PID (Process ID).
  - `kill -9 <pid>` : Force la terminaison du processus.

- `killall <nom>` : Termine tous les processus avec le même nom.

- `nice` : Définit la priorité d’un processus lors de son lancement.

- `renice` : Change la priorité d'un processus en cours d'exécution.

### 4. **Gestion du système de fichiers**
Ces commandes permettent de manipuler les disques, partitions, et systèmes de fichiers.

- `df -h` : Affiche l’espace disque utilisé et disponible dans les partitions.
  
- `du -sh <fichier/dossier>` : Affiche la taille d’un fichier ou d’un répertoire.

- `mount <dispositif> <point_de_montage>` : Monte un système de fichiers.
  
- `umount <point_de_montage>` : Démontre un système de fichiers.

- `fsck` : Vérifie et répare un système de fichiers.

- `fdisk` : Gère les partitions de disque.

- `mkfs.ext4 <dispositif>` : Formate un disque en ext4.

### 5. **Réseau**
Ces commandes permettent de gérer et vérifier les connexions réseau.

- `ping <adresse>` : Vérifie la connectivité avec une adresse IP ou un domaine.

- `ifconfig` ou `ip a` : Affiche les informations réseau.

- `netstat` ou `ss` : Affiche les connexions réseau.

- `curl <url>` ou `wget <url>` : Télécharge du contenu depuis une URL.

- `traceroute <adresse>` : Affiche le chemin réseau vers une adresse IP ou un domaine.

- `nslookup <domaine>` ou `dig <domaine>` : Recherche des informations DNS sur un domaine.

- `scp <fichier> <utilisateur>@<hôte>:<chemin>` : Copie sécurisée de fichiers entre des machines via SSH.

- `ssh <utilisateur>@<hôte>` : Se connecte à une machine distante via SSH.

### 6. **Utilisateurs et permissions**
Ces commandes permettent de gérer les utilisateurs, groupes, et permissions.

- `chmod <permissions> <fichier>` : Change les permissions d’un fichier (ex: `chmod 755`).
  
- `chown <propriétaire>:<groupe> <fichier>` : Change le propriétaire d’un fichier.

- `useradd <nom_utilisateur>` : Crée un nouvel utilisateur.

- `passwd <nom_utilisateur>` : Modifie le mot de passe d’un utilisateur.

- `usermod -aG <groupe> <nom_utilisateur>` : Ajoute un utilisateur à un groupe.

- `whoami` : Affiche le nom de l’utilisateur courant.

- `groups <nom_utilisateur>` : Liste les groupes auxquels appartient un utilisateur.

- `sudo <commande>` : Exécute une commande en tant qu’administrateur.

- `su <nom_utilisateur>` : Change l’utilisateur actuel.

### 7. **Archives et compression**
Ces commandes permettent de manipuler des fichiers compressés et des archives.

- `tar -cvf archive.tar <fichiers>` : Crée une archive tar.
  - `tar -xvf archive.tar` : Extrait une archive tar.
  
- `gzip <fichier>` : Compresse un fichier avec gzip.
  - `gunzip <fichier.gz>` : Décompresse un fichier gzip.

- `zip <archive.zip> <fichier>` : Crée une archive zip.
  - `unzip <archive.zip>` : Extrait une archive zip.

### 8. **Système**
Ces commandes permettent de gérer des aspects essentiels du système d'exploitation.

- `uname -r` : Affiche la version du noyau.
  
- `uptime` : Affiche le temps depuis le dernier démarrage.

- `shutdown` : Éteint ou redémarre le système.
  - `shutdown -h now` : Éteint immédiatement.
  - `shutdown -r now` : Redémarre immédiatement.
  
- `reboot` : Redémarre le système.

- `dmesg` : Affiche les messages du noyau.

- `history` : Affiche l'historique des commandes utilisées.

- `alias` : Crée un raccourci pour une commande (ex: `alias ll='ls -l'`).

### 9. **Gestion des packages (selon le système de gestion des paquets)**
Les systèmes Linux utilisent différents gestionnaires de paquets.

- **apt (pour les distributions basées sur Debian/Ubuntu)** :
  - `sudo apt update` : Met à jour la liste des paquets.
  - `sudo apt upgrade` : Met à jour tous les paquets installés.
  - `sudo apt install <paquet>` : Installe un paquet.
  - `sudo apt remove <paquet>` : Supprime un paquet.

- **yum (pour les distributions basées sur RedHat/CentOS)** :
  - `sudo yum install <paquet>` : Installe un paquet.
  - `sudo yum update` : Met à jour les paquets installés.
  - `sudo yum remove <paquet>` : Supprime un paquet.

- **dnf (successeur de yum)** :
  - `sudo dnf install <paquet>` : Installe un paquet.
  - `sudo dnf update` : Met à jour les paquets installés.
  
- **pacman (pour Arch Linux)** :
  - `sudo pacman -Syu` : Met à jour le système et les paquets.
  - `sudo pacman -S <paquet>` : Installe un paquet.

### 10. **Divers**
Quelques commandes utiles dans des contextes variés.

- `echo <texte>` : Affiche du texte dans le terminal.
- `date` : Affiche la date et l'heure.
- `cal

` : Affiche un calendrier.
- `clear` : Nettoie l’écran du terminal.

Ces commandes couvrent les bases et plusieurs aspects avancés de l'utilisation d'un système Linux. 
