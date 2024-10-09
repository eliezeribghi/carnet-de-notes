Voici quelques combinaisons de commandes Linux fréquemment utilisées qui montrent comment enchaîner plusieurs commandes pour accomplir des tâches courantes plus efficacement. Ces combinaisons utilisent souvent des opérateurs comme `|` (pipe) pour rediriger la sortie d'une commande vers une autre, ou `&&` pour exécuter une commande seulement si la précédente réussit.

### 1. **Lister et rechercher des fichiers contenant un motif spécifique**
Pour lister les fichiers et rechercher une chaîne de texte particulière dans ces fichiers, tu peux utiliser la combinaison de `ls` et `grep` :
```bash
ls | grep <motif>
```
Exemple :
```bash
ls | grep ".txt"
```
Cela liste tous les fichiers avec l'extension `.txt`.

### 2. **Rechercher un motif dans plusieurs fichiers**
Pour rechercher un motif dans tous les fichiers d'un répertoire et afficher les lignes contenant ce motif, tu peux utiliser `grep` avec l'option récursive `-r` :
```bash
grep -r "motif" *
```
Exemple :
```bash
grep -r "error" /var/log
```
Cela recherche toutes les occurrences de "error" dans les fichiers du répertoire `/var/log`.

### 3. **Combiner `find` et `grep` pour rechercher dans des fichiers spécifiques**
Pour rechercher un motif dans des fichiers trouvés par `find`, tu peux rediriger les résultats vers `grep` :
```bash
find <dossier> -name "*.log" | xargs grep "motif"
```
Exemple :
```bash
find /var/log -name "*.log" | xargs grep "error"
```
Cela trouve tous les fichiers `.log` dans `/var/log` et recherche "error" dans ces fichiers.

### 4. **Afficher et suivre la fin d’un fichier tout en filtrant**
Tu peux combiner `tail -f` pour suivre un fichier en temps réel et filtrer certaines lignes avec `grep` :
```bash
tail -f <fichier> | grep "<motif>"
```
Exemple :
```bash
tail -f /var/log/syslog | grep "error"
```
Cela suit le fichier `syslog` et affiche uniquement les nouvelles lignes contenant "error".

### 5. **Combiner plusieurs commandes avec `&&`**
Pour exécuter plusieurs commandes successives, et ne lancer la suivante que si la précédente réussit :
```bash
commande1 && commande2 && commande3
```
Exemple :
```bash
mkdir nouveau_dossier && cd nouveau_dossier && touch fichier.txt
```
Cela crée un nouveau dossier, se déplace dedans, puis crée un fichier `fichier.txt`.

### 6. **Rediriger la sortie d’une commande vers un fichier**
Pour rediriger la sortie d’une commande dans un fichier :
```bash
commande > fichier.txt
```
Exemple :
```bash
ls -l > fichier.txt
```
Cela enregistre la liste détaillée des fichiers dans `fichier.txt`. Pour ajouter à un fichier sans écraser :
```bash
ls -l >> fichier.txt
```

### 7. **Combinaison `find` et suppression de fichiers**
Pour trouver et supprimer tous les fichiers correspondant à un motif particulier, tu peux combiner `find` et `rm` :
```bash
find <dossier> -name "<motif>" -exec rm {} \;
```
Exemple :
```bash
find . -name "*.tmp" -exec rm {} \;
```
Cela supprime tous les fichiers `.tmp` dans le répertoire courant.

### 8. **Afficher l’utilisation du disque et trier par taille**
Pour afficher l’utilisation du disque dans un répertoire et trier par taille, tu peux combiner `du` et `sort` :
```bash
du -h <dossier> | sort -h
```
Exemple :
```bash
du -h /home/user | sort -h
```
Cela affiche l’utilisation du disque dans `/home/user`, triée par taille croissante.

### 9. **Lister les processus et trier par utilisation de la mémoire**
Pour lister les processus et les trier par utilisation de la mémoire, tu peux utiliser `ps` et `sort` :
```bash
ps aux --sort=-%mem
```
Cela affiche tous les processus triés par utilisation de la mémoire de manière décroissante.

### 10. **Compresser et archiver des fichiers avec `tar` et `gzip`**
Tu peux combiner `tar` et `gzip` pour créer une archive compressée :
```bash
tar -cvf archive.tar fichier1 fichier2 | gzip archive.tar
```
Exemple :
```bash
tar -cvf mes_fichiers.tar /home/user/Documents | gzip mes_fichiers.tar
```
Cela crée une archive de tous les fichiers dans `/home/user/Documents` et la compresse avec `gzip`.

### 11. **Suivi des connexions réseau et filtrage des résultats**
Tu peux utiliser `netstat` ou `ss` et filtrer les connexions spécifiques avec `grep` :
```bash
netstat -tuln | grep LISTEN
```
Cela affiche uniquement les connexions réseau en écoute sur le système.

### 12. **Déplacer et renommer plusieurs fichiers avec `mv` et `find`**
Pour déplacer plusieurs fichiers d’un répertoire vers un autre en utilisant `find` et `mv` :
```bash
find <dossier_source> -name "*.txt" -exec mv {} <dossier_destination> \;
```
Exemple :
```bash
find /home/user/downloads -name "*.txt" -exec mv {} /home/user/documents \;
```
Cela déplace tous les fichiers `.txt` de `downloads` à `documents`.

### 13. **Exécuter une commande dans plusieurs fichiers en parallèle**
Pour exécuter une commande sur plusieurs fichiers trouvés par `find`, tu peux utiliser `xargs` pour paralléliser les commandes :
```bash
find <dossier> -name "<motif>" | xargs -I {} commande {}
```
Exemple :
```bash
find . -name "*.txt" | xargs -I {} cp {} backup/
```
Cela copie tous les fichiers `.txt` trouvés dans le répertoire `backup`.

### 14. **Surveiller l’utilisation du système et sauvegarder les données**
Pour surveiller l’utilisation des ressources système avec `top` et enregistrer les résultats dans un fichier :
```bash
top -b -n 1 > system_report.txt
```
Cela enregistre une capture instantanée des processus en cours dans un fichier texte.

### 15. **Afficher les informations du système et filtrer des informations spécifiques**
Tu peux afficher les informations sur le noyau et les filtrer avec `grep` :
```bash
uname -a | grep "<motif>"
```
Exemple :
```bash
uname -a | grep "Linux"
```
Cela affiche les informations sur la version du noyau contenant le mot "Linux".

Ces combinaisons permettent de réaliser des tâches courantes en DevOps et administration système plus efficacement et peuvent être adaptées selon tes besoins spécifiques.
