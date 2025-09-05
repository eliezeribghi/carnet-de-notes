 guide complet sur les commandes et les raccourcis essentiels pour **Vim**,
 Il est important de noter que Vim a trois modes principaux : **Normal**, **Insertion** et **Commande**. Chaque mode a ses propres commandes.

### Modes de Vim
1. **Normal Mode** : Mode par défaut pour naviguer et exécuter des commandes.
2. **Insert Mode** : Mode pour insérer du texte.
3. **Command Mode** : Mode pour exécuter des commandes (accessible en appuyant sur `:` en Normal Mode).

### Changer de mode
- **Normal Mode** : Appuyer sur `Esc` (si tu es en Insert Mode).
- **Insert Mode** : Appuyer sur `i` (Insérer avant le curseur) ou `a` (Insérer après le curseur).
- **Command Mode** : Appuyer sur `:` en Normal Mode.

### Commandes de base
#### Navigation
- **h** : Déplacer le curseur vers la gauche.
- **j** : Déplacer le curseur vers le bas.
- **k** : Déplacer le curseur vers le haut.
- **l** : Déplacer le curseur vers la droite.
- **0** : Aller au début de la ligne.
- **^** : Aller au premier caractère non blanc de la ligne.
- **$** : Aller à la fin de la ligne.
- **gg** : Aller au début du fichier.
- **G** : Aller à la fin du fichier.
- **:n** : Aller à la ligne `n` (ex. `:10` pour aller à la ligne 10).
- **Ctrl + f** : Avancer d'une page.
- **Ctrl + b** : Reculer d'une page.
- **Ctrl + d** : Descendre d'une demi-page.
- **Ctrl + u** : Remonter d'une demi-page.
- **%** : Aller à la parenthèse, accolade ou crochet correspondant.

#### Édition de texte
- **i** : Passer en Insert Mode avant le curseur.
- **a** : Passer en Insert Mode après le curseur.
- **o** : Ouvrir une nouvelle ligne en dessous et entrer en Insert Mode.
- **O** : Ouvrir une nouvelle ligne au-dessus et entrer en Insert Mode.
- **x** : Supprimer le caractère sous le curseur.
- **dd** : Supprimer la ligne courante.
- **d$** : Supprimer de la position du curseur à la fin de la ligne.
- **d0** : Supprimer de la position du curseur au début de la ligne.
- **yy** : Copier (yanker) la ligne courante.
- **p** : Coller après le curseur.
- **P** : Coller avant le curseur.
- **u** : Annuler la dernière action.
- **Ctrl + r** : Rétablir l'action annulée.
- **J** : Joindre la ligne courante à la ligne suivante.

#### Recherche et remplacement
- **/** : Recherche vers l'avant.
- **?** : Recherche vers l'arrière.
- **n** : Trouver la prochaine occurrence.
- **N** : Trouver l'occurrence précédente.
- **:%s/ancien/nouveau/g** : Remplacer toutes les occurrences de `ancien` par `nouveau` dans tout le fichier.
- **:s/ancien/nouveau/g** : Remplacer toutes les occurrences de `ancien` par `nouveau` dans la ligne courante.

#### Gestion des fichiers
- **:w** : Enregistrer le fichier.
- **:wq** : Enregistrer et quitter.
- **:q** : Quitter (sans enregistrer si aucune modification a été faite).
- **:q!** : Quitter sans enregistrer les modifications.
- **:e fichier** : Ouvrir un autre fichier.
- **:w nom_fichier** : Enregistrer sous un nouveau nom.
- **:set number** : Afficher les numéros de ligne.
- **:set nonumber** : Masquer les numéros de ligne.

### Raccourcis et combinaisons
- **Ctrl + v** : Passer en mode Visual Block pour sélectionner un bloc de texte.
- **v** : Passer en mode Visuel pour sélectionner du texte.
- **V** : Passer en mode Visuel Ligne pour sélectionner des lignes entières.
- **d** suivi de **v** ou **V** : Supprimer le texte sélectionné.
- **y** suivi de **v** ou **V** : Copier le texte sélectionné.
- **>** : Indenter la ligne courante ou le texte sélectionné.
- **<** : Désindenter la ligne courante ou le texte sélectionné.
- **gq** : Formater le texte (utile pour la justification).

### Macros
- **q** suivi d'une lettre (ex. `qa`) : Commencer à enregistrer une macro dans le registre `a`.
- **q** : Arrêter l'enregistrement de la macro.
- **@a** : Exécuter la macro enregistrée dans le registre `a`.
- **@@** : Réexécuter la dernière macro.

### Fenêtres et onglets
- **:split** ou **:sp** : Diviser la fenêtre horizontalement.
- **:vsplit** ou **:vsp** : Diviser la fenêtre verticalement.
- **Ctrl + w, h/j/k/l** : Naviguer entre les fenêtres (gauche, bas, haut, droite).
- **:tabnew** : Ouvrir un nouvel onglet.
- **:tabnext** ou **:tabn** : Passer à l'onglet suivant.
- **:tabprev** ou **:tabp** : Passer à l'onglet précédent.

### Exécuter des commandes shell
- **:!commande** : Exécuter une commande shell (par exemple `:!ls`).
- **Ctrl + Z** : Mettre Vim en arrière-plan.
- **:sh** : Passer en mode shell.

### Personnalisation et configuration
- **.vimrc** : Fichier de configuration personnel pour Vim, où tu peux ajouter des options de personnalisation.

### Commandes avancées
- **:set syntax=on** : Activer la coloration syntaxique.
- **:set autoindent** : Activer l'indentation automatique.
- **:set expandtab** : Convertir les tabulations en espaces.
- **:set tabstop=4** : Définir la largeur d'une tabulation à 4 espaces.

### Conclusion
Vim est un éditeur très puissant qui offre une multitude de commandes et de fonctionnalités. 
