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
		
        // Variables envoyées à la page views/tasks/show.php :
        return $this->render('show', [
            'tasks' => $tasks,
            'completedTaskCount' => $completedTaskCount,
            'contentContainer' => $this->contentContainer,
            'canCreateNewTasks' => $canCreateNewTasks,
            
        ]);
    }
	// fonction exécutée lorsqu'on clique sur SAVE dans edit.php 
    public function actionEdit() {

        $id = (int) Yii::$app->request->get('id'); // on récupère l'id de la tâche
        $parent = (int) Yii::$app->request->get('parent'); // on récupère l'id de la tâche parente 
        if($parent!=null) {
			$tacheMere = Task::find()->contentContainer($this->contentContainer)->readable()->where(['task.id' => $parent])->one();
		}
		// On récupère la date limite pour l'afficher dans la page edit.php
        $datelimite=Yii::$app->request->get('datelimite');
        // on récupère la tâche en cours, grâce à l'id passé en paramètre ($id)
        $task = Task::find()->contentContainer($this->contentContainer)->readable()->where(['task.id' => $id])->one();
		
		/* Création d'une nouvelle tâche */
		
        if ($task === null && $parent == null) {
			
            // Check permission de créer un nouveau travail
				if (!$this->contentContainer->permissionManager->can(new \humhub\modules\tasks\permissions\CreateTask())) {
					throw new HttpException(400, 'Accès refusé, tu crois quoi ?!');
				}
				// on récupère la valeur la plus haute de droite 
				$derniereTache = Task::find()->contentContainer($this->contentContainer)->readable()->orderBy(['droite'=>SORT_DESC])->one();
				
				// on crée un nouvel objet 'tâche'
				$task = new Task();				
				// on passe son statut à 1 (1 = en cours, 5 = terminé)
				$task->status = 1;
				// si la dernière tâche n'existe pas (si c'est la première insertion dans la table)
				if ($derniereTache == NULL) {
					$task->gauche = 1;
					$task->droite = 2;
				} else { // Si on ajoute une nouvelle tâche, on adapte les valeurs de gauche et droite
					$maxDroite = $derniereTache->droite;
					$task->gauche = $maxDroite + 1;
					$task->droite = $maxDroite + 2;
				}
				
				// on spécifie que cette tâche appartient au contentContainer
				$task->content->container = $this->contentContainer;
				
			}
				
		/* Ajout d'une sous-tâche de niveau 1 */
		
		if ($task === null && $parent != null) {
				$tmDroite = $tacheMere->droite;
				$tmGauche = $tacheMere->gauche;
				
			// Check permission to create new task
				if (!$this->contentContainer->permissionManager->can(new \humhub\modules\tasks\permissions\CreateTask())) {
					throw new HttpException(400, 'Access denied!');
				}
			// On augmente de 2 partout à droite de la tâche mère, + la tâche mère elle-même pour laisser de la place à la sous-tâche
				Task::updateAllCounters(['droite' => 2], 'droite >= ' . $tmDroite);
				Task::updateAllCounters(['gauche' => 2], 'gauche > ' . $tmDroite);
			// Nouvel objet tâche
				$task = new Task();
				$task->status = 1 ; // en cours
				
			// on précise le content container
				$task->content->container = $this->contentContainer;
			// La gauche de la sous tache prend la droite de la tache
				$task->gauche = $tmDroite;
			// Sa droite prend sa droite + 1
				$task->droite = $tmDroite+1;			
		}
		
		// on envoie les modifications dans la base et on regarde si ça passe, si oui redirection vers la page show.php
        if ($task->load(Yii::$app->request->post())) {
            if ($task->validate()) {
                if ($task->save()) {
				// On affiche la page show.php
                    return $this->htmlRedirect($this->contentContainer->createUrl('show'));
                }
            }
        }
        
		// On affiche la page edit.php en passant l'objet tâche et l'id de la tâche parente en paramètres
        return $this->renderAjax('edit', ['task'=>$task,'parent'=>$parent,'datelimite'=>$datelimite]);

    }

	// Fonction exécutée à la suppression d'une tâche
    public function actionDelete() {
		// On récupère l'id de la tâche
        $id = (int) Yii::$app->request->get('id');
	
        if ($id != 0) {
            $task = Task::find()->contentContainer($this->contentContainer)->where(['task.id' => $id])->one();
            if ($task) {
				
				$droite = $task->droite;
				$gauche = $task->gauche;
				$largeur = $droite - $gauche + 1;
				
				$tasksToDelete = Task::find()->contentContainer($this->contentContainer)->where(['between','task.gauche',$task->gauche,$task->droite])->all();
				if ($tasksToDelete) {
					// on supprime toutes les sous-tâches !
					foreach ($tasksToDelete as $tasky) {
						$tasky->delete();
					}
					// On met à jour les valeurs de gauche et droite 
				Task::updateAllCounters(['droite' => -$largeur], 'droite > ' . $droite);
				Task::updateAllCounters(['gauche' => -$largeur], 'gauche > ' . $droite);

				}
				else $task->delete();
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
        $subtasks = $task->getCheminComplet();
        $status = (int) Yii::$app->request->get('status');
        $task->changeStatus($status);
        if($subtasks!=null) {foreach($subtasks as $st){$st->changeStatus($status);}}
        
        return $this->renderTask($task);
    }
    // Ajout changer la priorité
    public function actionChangePriority()
    {
		$task = $this->getTaskById((int) Yii::$app->request->get('taskId'));
		$priority = (int) Yii::$app->request->get('priority');
		$task->changePriority($priority);
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
	
	/* Récupérer la profondeur d'un noeud : 
	 
	SELECT node.title, (
	COUNT( parent.id ) -1
	) AS depth
	FROM task AS node, task AS parent
	WHERE node.gauche
	BETWEEN parent.gauche
	AND parent.droite
	AND id = $task->id
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
                GROUP BY node.id
                ORDER BY node.gauche
        )AS sub_tree
		WHERE node.gauche BETWEEN parent.gauche AND parent.droite
        AND node.gauche BETWEEN sub_parent.gauche AND sub_parent.droite
		AND sub_parent.title = sub_tree.title
        
		GROUP BY node.id
		ORDER BY node.gauche;
		 
		 /* "traduction yii"
				$sub_query=(new \Yii\db\Query())
	           ->select('snode.title,(COUNT(parent.id) - 1) AS depth')
	           ->from(['node'=>'task','parent'=>'task'])
	           ->where(['between','node.gauche','parent.gauche','parent.droite'])
	           ->andWhere(['node.id'=>$task->id])
	           ->groupBy('node.id')
	           ->orderBy('node.gauche');
	           
				$profondeur = (new \Yii\db\Query())
				->select('node.title,(COUNT(parent.id) - (sub_tree.depth + 1)) as depth')
				->from(['node' => 'task', 'parent'=>'task','sub_parent'=>'task','sub_tree'=>$sub_query])
				->where(['between','node.gauche','parent.gauche','parent.droite'])
				->andWhere(['between','node.gauche','sub_parent.gauche','sub_parent.droite'])
				->andWhere(['sub_parent.title'=>'sub_tree.title'])
				->groupBy(['node.id'])
				->orderBy('node.gauche')
				->one();
				$marge = 25 * $profondeur; 
				echo "prof : " . $profondeur['depth']; 
				* 
				* Validation date de fin de sous-tâche: 
				* public function validateEndTime($attribute, $params)
    {
        if (new \DateTime($this->start_datetime) >= new \DateTime($this->end_datetime)) {
            $this->addError($attribute, Yii::t('CalendarModule.base', "End time must be after start time!"));
        }
        * 
        * 
        *    Récupérer les enfants directs d'un noeud : 
        * 
        * SELECT node.title, (COUNT(parent.title) - (sub_tree.depth + 1)) as depth,node.percent AS progression
			FROM task AS node,
					task AS parent,
					task AS sub_parent,
					(
							SELECT node.title, (COUNT(parent.title) - 1) AS depth
							FROM task AS node,
									task AS parent
							WHERE node.gauche BETWEEN parent.gauche AND parent.droite
									AND node.id = 3
							GROUP BY node.id
							ORDER BY node.gauche
					)AS sub_tree
			WHERE node.gauche BETWEEN parent.gauche AND parent.droite
					AND node.gauche BETWEEN sub_parent.gauche AND sub_parent.droite
					AND sub_parent.title = sub_tree.title
			GROUP BY node.id
			HAVING depth = 1
			ORDER BY node.gauche
			* 
			* 
			*   SUPPRESSION 
			*   ===========
			* 
			* LOCK TABLE nested_category WRITE;

			SELECT @myLeft := lft, @myRight := rgt, @myWidth := rgt - lft + 1
			FROM nested_category
			WHERE name = 'MP3 PLAYERS';

			DELETE FROM nested_category WHERE lft BETWEEN @myLeft AND @myRight;

			UPDATE nested_category SET rgt = rgt - @myWidth WHERE rgt > @myRight;
			UPDATE nested_category SET lft = lft - @myWidth WHERE lft > @myRight;

			UNLOCK TABLES;
			* 
			* 
    }*/
}
