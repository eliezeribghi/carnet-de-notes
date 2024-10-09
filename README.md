
### 1. **Commandes Unix / Linux**
Ces commandes sont la base pour tout environnement serveur.

- **Gestion des fichiers et dossiers**:
  - `ls`: liste les fichiers dans un répertoire.
  - `cd`: change de répertoire.
  - `mkdir`: crée un répertoire.
  - `rm`: supprime des fichiers ou répertoires (`rm -rf` pour supprimer de manière récursive).
  - `cp`: copie des fichiers.
  - `mv`: déplace ou renomme des fichiers.
  - `chmod`: modifie les permissions des fichiers.
  - `chown`: modifie le propriétaire et le groupe d'un fichier.

- **Gestion des processus**:
  - `ps`: affiche les processus en cours d'exécution.
  - `top` ou `htop`: surveille les performances du système.
  - `kill` ou `killall`: termine un processus en fonction de son PID ou son nom.
  - `systemctl`: gère les services (start, stop, status).
  
- **Gestion réseau**:
  - `ifconfig` ou `ip a`: affiche les informations réseau.
  - `ping`: vérifie la connectivité réseau.
  - `netstat` ou `ss`: affiche les connexions réseau.
  - `nslookup`: interroge les serveurs DNS.
  - `curl` ou `wget`: effectue des requêtes HTTP ou télécharge des fichiers.

- **Archive & Compression**:
  - `tar -cvf archive.tar fichier`: crée une archive.
  - `tar -xvf archive.tar`: extrait une archive.
  - `gzip fichier`: compresse un fichier avec Gzip.

### 2. **Commandes Git (Gestion de versions)**
Git est fondamental pour la gestion du code source.

- `git init`: initialise un dépôt Git.
- `git clone <url>`: clone un dépôt distant.
- `git status`: vérifie l'état du dépôt.
- `git add <fichier>`: ajoute un fichier à l'index (staging).
- `git commit -m "message"`: enregistre les modifications avec un message.
- `git push`: envoie les modifications vers le dépôt distant.
- `git pull`: récupère et intègre les modifications depuis le dépôt distant.
- `git branch`: liste ou crée des branches.
- `git checkout <branche>`: bascule vers une branche.
- `git merge <branche>`: fusionne une branche dans la branche courante.

### 3. **Docker (Gestion des conteneurs)**
Les conteneurs sont essentiels en DevOps.

- **Gestion des conteneurs**:
  - `docker run <image>`: lance un conteneur depuis une image.
  - `docker ps`: liste les conteneurs en cours d'exécution.
  - `docker stop <container>`: arrête un conteneur.
  - `docker rm <container>`: supprime un conteneur.
  - `docker exec -it <container> bash`: accède à un conteneur en mode interactif.
  
- **Gestion des images**:
  - `docker build -t <nom_image> .`: construit une image depuis un `Dockerfile`.
  - `docker pull <image>`: télécharge une image depuis Docker Hub.
  - `docker rmi <image>`: supprime une image.
  - `docker images`: liste les images disponibles.

- **Gestion des volumes**:
  - `docker volume create <volume>`: crée un volume.
  - `docker volume ls`: liste les volumes.
  - `docker volume rm <volume>`: supprime un volume.

### 4. **Kubernetes (Orchestration de conteneurs)**
Pour la gestion des clusters de conteneurs.

- `kubectl get pods`: liste les pods dans le cluster.
- `kubectl get nodes`: liste les nœuds du cluster.
- `kubectl apply -f <fichier.yaml>`: déploie une configuration Kubernetes.
- `kubectl delete pod <pod_name>`: supprime un pod.
- `kubectl logs <pod_name>`: affiche les logs d'un pod.
- `kubectl exec -it <pod_name> -- bash`: accède à un pod en mode interactif.

### 5. **Ansible (Automatisation de configuration)**
Outil d'automatisation pour la gestion des serveurs.

- `ansible -i hosts all -m ping`: vérifie la connectivité avec tous les hôtes définis dans le fichier `hosts`.
- `ansible-playbook playbook.yml`: exécute un playbook Ansible.
- `ansible-galaxy install <role>`: installe un rôle depuis Ansible Galaxy.

### 6. **Terraform (Infrastructure as Code)**
Outil pour gérer l'infrastructure via du code.

- `terraform init`: initialise un répertoire pour Terraform.
- `terraform plan`: génère et affiche un plan d'exécution.
- `terraform apply`: applique les modifications d'infrastructure.
- `terraform destroy`: détruit l'infrastructure gérée par Terraform.

### 7. **CI/CD (Intégration et déploiement continu)**
Commandes utilisées dans des pipelines CI/CD comme Jenkins, GitLab CI, etc.

- **Jenkins CLI**:
  - `java -jar jenkins-cli.jar -s http://localhost:8080/ build <job_name>`: lance un job Jenkins.
  - `java -jar jenkins-cli.jar -s http://localhost:8080/ list-jobs`: liste les jobs dans Jenkins.
  
- **GitLab Runner**:
  - `gitlab-runner register`: enregistre un nouveau runner.
  - `gitlab-runner start`: démarre le runner.
  - `gitlab-runner stop`: arrête le runner.

### 8. **AWS CLI (Amazon Web Services)**
Pour interagir avec les services AWS.

- `aws s3 ls`: liste les buckets S3.
- `aws ec2 describe-instances`: affiche les informations des instances EC2.
- `aws s3 cp <fichier> s3://<bucket>/`: copie un fichier dans un bucket S3.
- `aws iam create-user --user-name <nom>`: crée un utilisateur IAM.

### 9. **Monitoring et Logs**
Surveiller les systèmes et visualiser les logs.

- **Logs**:
  - `tail -f /var/log/syslog`: affiche les logs système en temps réel.
  - `journalctl -xe`: affiche les logs détaillés pour les services systemd.
  
- **Surveillance système**:
  - `vmstat`: affiche l'utilisation des ressources.
  - `iostat`: surveille les statistiques d'entrées/sorties.
  - `df -h`: affiche l'utilisation des disques.

### 10. **Autres outils utiles**
- **Vagrant**: gestion d'environnements de développement.
  - `vagrant up`: démarre une machine virtuelle.
  - `vagrant halt`: arrête la machine virtuelle.
  - `vagrant ssh`: accède à la machine virtuelle.

- **Nginx**:
  - `nginx -t`: vérifie la syntaxe du fichier de configuration Nginx.
  - `sudo systemctl reload nginx`: recharge la configuration Nginx sans redémarrer le service.

