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

        $tasks = Task::find()->contentContainer($this->contentContainer)->readable()->all();
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
		$tacheMere = Task::find()->contentContainer($this->contentContainer)->readable()->where(['task.id'=> $parent])->one();
		

		// Si la tâche n'existe pas (donc si on en crée une nouvelle)
        if ($task === null && $parent == 0) {
			
            // Check permission to create new task
				if (!$this->contentContainer->permissionManager->can(new \humhub\modules\tasks\permissions\CreateTask())) {
					throw new HttpException(400, 'Access denied!');
				}
				// on récupère la dernière tâche 
				$derniereTache = Task::find()->contentContainer($this->contentContainer)->readable()->orderBy(['id'=>SORT_DESC])->one();
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
		// Ajout d'une sous-tâche
		if ($task === null && $parent != 0) {
			
			// Check permission to create new task
				if (!$this->contentContainer->permissionManager->can(new \humhub\modules\tasks\permissions\CreateTask())) {
					throw new HttpException(400, 'Access denied!');
				}
				$task = new Task();
				$task->status = 1 ; // en cours
				
				//Task::updateAllCounters(['gauche' => 2], ['>=', $maDroite]);
				$task->gauche = $maDroite;
				$task->droite = $maDroite + 1;
				$task->content->container = $this->contentContainer;
				
				
		}
		
		// on envoie les modifications dans la base et on regarde si ça passe, si oui redirection vers la page show.php
        if ($task->load(Yii::$app->request->post())) {
            if ($task->validate()) {
                if ($task->save()) {
					Task::updateAllCounters(['gauche' => 2], 'gauche' < 10); 
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




    public function actionAssign()
    {
        $task = $this->getTaskById((int) Yii::$app->request->get('taskId'));
        $task->assignUser();
        return $this->renderTask($task);
    }

    public function actionUnAssign()
    {
        $task = $this->getTaskById((int) Yii::$app->request->get('taskId'));
        $task->unassignUser();
        return $this->renderTask($task);
    }

    public function actionChangePercent()
    {
        $task = $this->getTaskById((int) Yii::$app->request->get('taskId'));
        $task->changePercent((int) Yii::$app->request->get('percent'));
        return $this->renderTask($task);
    }

    public function actionChangeStatus()
    {
        $task = $this->getTaskById((int) Yii::$app->request->get('taskId'));
        $status = (int) Yii::$app->request->get('status');
        $task->changeStatus($status);
        return $this->renderTask($task);
    }

    protected function renderTask($task)
    {
        Yii::$app->response->format = 'json';
        $json = array();
        $json['output'] = $this->renderAjaxContent($task->getWallOut());
        $json['wallEntryId'] = $task->content->getFirstWallEntryId();
        return $json;
    }

    protected function getTaskById($id)
    {
        $task = Task::find()->contentContainer($this->contentContainer)->readable()->where(['task.id' => $id])->one();
        if ($task === null) {
            throw new HttpException(404, "Could not load task!");
        }
        return $task;
    }

}
