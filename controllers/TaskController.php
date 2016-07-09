<?php

namespace humhub\modules\tasks\controllers;

use Yii;
use yii\web\HttpException;
use humhub\modules\content\components\ContentContainerController;
use humhub\modules\tasks\models\Task;

class TaskController extends ContentContainerController
{

    public $hideSidebar = true;

    public function actionShow()
    {

        $tasks = Task::find()->contentContainer($this->contentContainer)->readable()->orderBy(['gauche'=>SORT_ASC])->all();
        $completedTaskCount = Task::find()->contentContainer($this->contentContainer)->readable()->where(['task.status' => 5])->count();
        $canCreateNewTasks = $this->contentContainer->permissionManager->can(new \humhub\modules\tasks\permissions\CreateTask());
        // A créer : profondeur -> niveau de la sous-tâche
        //           subtaskcount -> nombre d'enfants
        // Variables envoyées à la page views/tasks/show.php :
        return $this->render('show', [
            'tasks' => $tasks,
            'completedTaskCount' => $completedTaskCount,
            'contentContainer' => $this->contentContainer,
            'canCreateNewTasks' => $canCreateNewTasks
        ]);


    }
	// fonction exécutée lorsqu'on clique sur SAVE dans edit.php 
    public function actionEdit() {

        $id = (int) Yii::$app->request->get('id'); // on récupère l'id de la tâche
        $parent = (int) Yii::$app->request->get('parent'); // on récupère l'id de la tâche parente 
        // on récupère la tâche en cours, grâce à l'id passé en paramètre ($id)
        $task = Task::find()->contentContainer($this->contentContainer)->readable()->where(['task.id' => $id])->one();
		
		if ($parent != null) {
			$tacheMere = Task::findOne($parent);
			$maDroite = $tacheMere->droite;
			$maGauche = $tacheMere->gauche;
		}
		
		/* Création d'un nouveau travail */
		
        if ($task === null && $parent == null) {
			
            // Check permission de créer un nouveau travail
				if (!$this->contentContainer->permissionManager->can(new \humhub\modules\tasks\permissions\CreateTask())) {
					throw new HttpException(400, 'Accès refusé, tu crois quoi ?!');
				}
				// on récupère la valeur la plus haute de droite 
				$derniereTache = Task::find()->contentContainer($this->contentContainer)->readable()->orderBy(['droite'=>SORT_DESC])->one();
				$maxDroite = $derniereTache->droite;
				// on crée un nouvel objet 'tâche'
				$task = new Task();
				// on passe son statut à 1 (1 = en cours, 5 = terminé)
				$task->status = 1;
				// si la dernière tâche n'existe pas (si c'est la première insertion dans la table)
				if ($derniereTache == NULL) {
					$task->gauche = 1;
					$task->droite = 2;
				} else { // Si on ajoute une nouvelle tâche, on adapte les valeurs de gauche et droite
					$task->gauche = $derniereTache->droite + 1;
					$task->droite = $task->gauche + 1;
				}
				
					
				// on spécifie que cette tâche appartient au contentContainer
				$task->content->container = $this->contentContainer;
			
        }
        
		/* Ajout d'une sous-tâche de niveau 1 */
		
		if ($task === null && $parent != null) {
			
				Task::updateAllCounters(['droite' => 2], 'droite > ' . $maDroite);
				Task::updateAllCounters(['gauche' => 2], 'gauche > ' . $maDroite);
			// Check permission to create new task
				if (!$this->contentContainer->permissionManager->can(new \humhub\modules\tasks\permissions\CreateTask())) {
					throw new HttpException(400, 'Access denied!');
				}
				$task = new Task();
				$task->status = 1 ; // en cours
				
				
				//$task->save();
				
				
				// on précise le content container
				$task->content->container = $this->contentContainer;
				//  On augmente toutes la droite de la tache mère de 2
				$tacheMere->droite = $maDroite + 2;
				// La gauche de la sous tache prend la droite de la tache
				$task->gauche = $maDroite;
				// Sa droite prend sa gauche + 1
				$task->droite = $maDroite + 1;
				$task->description = "" . $maDroite;
		}
		
		// on envoie les modifications dans la base et on regarde si ça passe, si oui redirection vers la page show.php
        if ($task->load(Yii::$app->request->post())) {
            if ($task->validate()) {
                if ($task->save()) {
					if($parent!=null){
					// On sauvegarde la modification de la tache mère
						$tacheMere->save();
				}
                    return $this->htmlRedirect($this->contentContainer->createUrl('show'));
                }
            }
        }
        
		// On affiche la page edit.php en passant l'objet tâche et l'id de la tâche parente en paramètres
        return $this->renderAjax('edit', ['task'=>$task,'parent'=>$parent]);

    }

	// Fonction exécutée à la suppression d'une tâche
    public function actionDelete() {
		// On récupère l'id de la tâche
        $id = (int) Yii::$app->request->get('id');

        if ($id != 0) {
            $task = Task::find()->contentContainer($this->contentContainer)->where(['task.id' => $id])->one();
            if ($task) {
                $task->delete();
            }
        }

        Yii::$app->response->format='json';
        return ['status'=>'ok'];
    }



	// Fonction pour assigner un utilisateur à une tâche
    public function actionAssign()
    {
        $task = $this->getTaskById((int) Yii::$app->request->get('taskId'));
        $task->assignUser();
        return $this->renderTask($task);
    }
	// Désassigner l'utilisateur
    public function actionUnAssign()
    {
        $task = $this->getTaskById((int) Yii::$app->request->get('taskId'));
        $task->unassignUser();
        return $this->renderTask($task);
    }
	// Changer la progression
    public function actionChangePercent()
    {
        $task = $this->getTaskById((int) Yii::$app->request->get('taskId'));
        $task->changePercent((int) Yii::$app->request->get('percent'));
        return $this->renderTask($task);
    }
	// Changer le statut
    public function actionChangeStatus()
    {
        $task = $this->getTaskById((int) Yii::$app->request->get('taskId'));
        $status = (int) Yii::$app->request->get('status');
        $task->changeStatus($status);
        return $this->renderTask($task);
    }
	// Afficher les modifications
    protected function renderTask($task)
    {
        Yii::$app->response->format = 'json';
        $json = array();
        $json['output'] = $this->renderAjaxContent($task->getWallOut());
        $json['wallEntryId'] = $task->content->getFirstWallEntryId();
        return $json;
    }
	// Récupérer une tâche par son id
    protected function getTaskById($id)
    {
        $task = Task::find()->contentContainer($this->contentContainer)->readable()->where(['task.id' => $id])->one();
        if ($task === null) {
            throw new HttpException(404, "Could not load task!");
        }
        return $task;
    }
	/* Récupérer une tâche et ses sous-tâches : 
	SELECT noeud.title
	FROM task AS noeud, task AS parent
	WHERE noeud.gauche
	BETWEEN parent.gauche
	AND parent.droite
	AND parent.id = 1
	ORDER BY noeud.gauche	*/
	
	/* Récupérer la profondeur de l'arbre entier : 
	 
	SELECT node.title, (
	COUNT( parent.id ) -1
	) AS depth
	FROM task AS node, task AS parent
	WHERE node.gauche
	BETWEEN parent.gauche
	AND parent.droite
	GROUP BY node.id
	ORDER BY node.gauche
	* 
	* 
	* 
	* Récupérer la profondeur d'un sous-arbre
	* 
	* SELECT node.title, (COUNT(parent.id) - (sub_tree.depth + 1)) AS depth
		FROM task AS node,
        task AS parent,
        task AS sub_parent,
        (
                SELECT node.title, (COUNT(parent.id) - 1) AS depth
                FROM task AS node,
                task AS parent
                WHERE node.gauche BETWEEN parent.gauche AND parent.droite
                AND node.id = $task->id
                GROUP BY node.title
                ORDER BY node.gauche
        )AS sub_tree
		WHERE node.gauche BETWEEN parent.gauche AND parent.droite
        AND node.gauche BETWEEN sub_parent.gauche AND sub_parent.droite
		AND sub_parent.title = sub_tree.title
        
		GROUP BY node.id
		ORDER BY node.gauche;
	*/
	
	
}
