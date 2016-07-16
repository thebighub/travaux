Travaux de groupe
==============

Gestionnaire avancé de tâches, basé sur le module [Tasks](https://github.com/humhub/humhub-modules-tasks) de [Humhub](https://www.humhub.org)

Fonctionnalités avant amélioration : 
----------------------------------
- Ajout de tâches (1 niveau) avec _échéance_, _utilisateurs assignés_, possibilité de _commenter_. 
- Ouvrir/Fermer/Réouvrir une tâche  

Après amélioration : 
-------------------
- Ajout de sous-tâches (autant de niveaux que souhaité)
- Ajout d'une description (avec bouton afficher/masquer description)
- Barre de progression : 
	- Si la tâche a des sous-tâches : moyenne de la progression de ses sous-tâches
	- Sinon, pourcentage défini par l'utilisateur
- Support de Markdown pour les descriptions et les commentaires (pour pouvoir écrire du code, colorisé par la syntaxe d'[highlight.js](https://highlightjs.org/))

	


TODO
----
- Finaliser la gestion des suppressions (si on termine une tâche, toutes les sous-tâches doivent être terminées ;   si on supprime une sous-tâche, elle ne doit pas passer en-dessous mais juste être barrée à hauteur de la tâche)
- Système de priorité
- Intégrer les tâches au calendrier. (plus tard)  

Credits
-------  
Merci à Mike Hillyer et son article [Managing hierarchical data in MySQL](http://mikehillyer.com/articles/managing-hierarchical-data-in-mysql/)
Pour plus d'informations :  

  <https://github.com/humhub/humhub-modules-tasks>  
  <https://github.com/thebighub/travaux>
