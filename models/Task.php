<?php

namespace humhub\modules\tasks\models;

use Yii;
use humhub\modules\user\models\User;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\tasks\models\TaskUser;

/**
 * This is the model class for table "task".
 *
 * The followings are the available columns in table 'task':
 * @property integer $id
 * @property string $title
 * @property string $description
 * @property string $deadline
 * @property integer $max_users
 * @property integer $percent
 * @property integer $gauche
 * @property integer $droite
 * @property string $created_at
 * @property integer $created_by
 * @property string $updated_at
 * @property integer $updated_by
 */
class Task extends ContentActiveRecord implements \humhub\modules\search\interfaces\Searchable
{

    public $assignedUserGuids = "";

    // Status
    const STATUS_OPEN = 1;
    const STATUS_FINISHED = 5;

    public $wallEntryClass = 'humhub\modules\tasks\widgets\WallEntry';
    public $autoAddToWall = true;

    public static function tableName()
    {
        return 'task';
    }

    public function rules()
    {
        return array(
            [['title'], 'required'],
            [['description'], 'string'],
            [['max_users'], 'integer'],
            [['deadline'], \humhub\libs\DbDateValidator::className(), 'format' => Yii::$app->params['formatter']['defaultDateFormat']],
            [['max_users', 'assignedUserGuids', 'description', 'percent'], 'safe'],
        );
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return array(
            'title' => Yii::t('TasksModule.base','Title'),
            'assignedUserGuids' => Yii::t('TasksModule.base','Assigned user(s)'),
            'deadline' => Yii::t('TasksModule.base','Deadline'),
            'description' => 'Description',
            'percent' => 'Progression',
        );
    }

    public function getTaskUsers()
    {
        $query = $this->hasMany(TaskUser::className(), ['task_id' => 'id']);
        return $query;
    }

    public function getAssignedUsers()
    {
        return $this->hasMany(User::className(), ['id' => 'user_id'])
                        ->viaTable('task_user', ['task_id' => 'id']);
    }

    public function beforeDelete()
    {
        foreach ($this->taskUsers as $taskUser) {
            $taskUser->delete();
        }

        return parent::beforeDelete();
    }

    public function getUrl()
    {
        return $this->content->container->createUrl('/tasks/task/show', array('id' => $this->id));
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);


        foreach (explode(",", $this->assignedUserGuids) as $userGuid) {
            $f = false;
            foreach ($this->assignedUsers as $user) {
                if ($user->guid == trim($userGuid)) {
                    $f = true;
                }
            }

            if ($f == false) {
                $this->assignUser(User::findOne(['guid' => trim($userGuid)]));
            }
        }


        foreach ($this->assignedUsers as $user) {
            if (strpos($this->assignedUserGuids, $user->guid) === false) {
                $this->unassignUser($user);
            }
        }
        
        // Ajout de la tâche dans le calendrier (test)
				/*
				$date = $this->deadline;
				$taskCal = new CalendarEntry();
				$taskCal->title = $this->title;
				$taskCal->description = $this->description;
				$taskCal->start_datetime = $this->deadline;
				$taskCal->end_datetime = $date->add(new DateInterval('P1D'));
				$date=$taskCal->end_datetime;
				$taskCal->end_datetime = $date->sub(new DateInterval('P1S'));
				$taskCal->all_day = 1;
				$taskCal->participation_mode = 0;
				// sauvegarde de la tâche
				$taskCal->save();
			*/
    }

    public function afterFind()
    {

        foreach ($this->assignedUsers as $user) {
            $this->assignedUserGuids .= $user->guid . ",";
        }


        return parent::afterFind();
    }

    public function assignUser($user = "")
    {
        if ($user != "") {

            $au = TaskUser::findOne(array('task_id' => $this->id, 'user_id' => $user->id));
            if ($au == null) {

                $au = new TaskUser;
                $au->task_id = $this->id;
                $au->user_id = $user->id;
                $au->save();

                return true;
            }
        }
        return false;
    }

    public function unassignUser($user = "")
    {
        if ($user == "")
            $user = Yii::$app->user->getIdentity();

        $au = TaskUser::findOne(array('task_id' => $this->id, 'user_id' => $user->id));
        if ($au != null && $au->delete()) {
            return true;
        }
        return false;
    }

    public function changePercent($newPercent)
    {
        if ($this->percent != $newPercent) {
            $this->percent = $newPercent;
            $this->save();
        }

        if ($newPercent == 100) {
            $this->changeStatus(Task::STATUS_FINISHED);
        }

        if ($this->percent != 100 && $this->status == Task::STATUS_FINISHED) {
            $this->changeStatus(Task::STATUS_OPEN);
        }

        return true;
    }

    public function changeStatus($newStatus)
    {
        $this->status = $newStatus;

        if ($newStatus == Task::STATUS_FINISHED) {

            $activity = new \humhub\modules\tasks\activities\Finished();
            $activity->source = $this;
            $activity->originator = Yii::$app->user->getIdentity();
            $activity->create();

            if ($this->created_by != Yii::$app->user->id) {
                $notification = new \humhub\modules\tasks\notifications\Finished();
                $notification->source = $this;
                $notification->originator = Yii::$app->user->getIdentity();
                $notification->send($this->content->user);
            }

            $this->percent = 100;
        } else {
            // Try to delete TaskFinishedNotification if exists
            $notification = new \humhub\modules\tasks\notifications\Finished();
            $notification->source = $this;
            $notification->delete($this->content->user);
        }

        $this->save();

        return true;
    }

    public function hasDeadline()
    {
        if ($this->deadline != '0000-00-00 00:00:00' && $this->deadline != '' && $this->deadline != 'NULL') {
            return true;
        }
        return false;
    }
     public static function GetUsersOpenTasks()
    {
        $query = self::find();
        $query->leftJoin('task_user', 'task.id=task_user.task_id');
        $query->where(['task_user.user_id' => Yii::$app->user->id, 'task.status' => self::STATUS_OPEN]);

        return $query->all();
    }

    /**
     * @inheritdoc
     */
    public function getContentName()
    {
        return Yii::t('TasksModule.models_Task', "Task");
    }

    /**
     * @inheritdoc
     */
    public function getContentDescription()
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public function getSearchAttributes()
    {
        return array(
            'title' => $this->title,
            'description' => $this->description,
        );
    }

    public function isOverdue()
    {
        if (!$this->hasDeadline()) {
            return false;
        }

        return (strtotime($this->deadline) < time());
    }


    /*   
     * Fonctions ajoutées 
     * 					  */
    
    /* La tâche a-t-elle des enfants ? */
    public function hasChild()
    {	$nbEnfants = Yii::$app->db->createCommand('SELECT COUNT(*) FROM task WHERE task.gauche BETWEEN ' . $this->gauche . ' AND ' . $this->droite)->queryOne();
		if ($nbEnfants > 1) {
		return true;
		}
		else return false;
	}
	/* Récupérer le nombre d'enfants */
	public function getChildrenNumber()
	{
		$req = Yii::$app->db->createCommand('SELECT COUNT(*) as compte FROM task WHERE task.gauche BETWEEN ' . $this->gauche . ' AND ' . $this->droite)->queryOne();
		return $req['compte']-1;
	}
	/* Récupérer la deadline de la tâche mère (pour valider l'échéance des sous-tâches (< fin tâche mère) ) */
	public function getMotherDeadline()
	{
		$req = Yii::$app->db->createCommand('
		SELECT parent.deadline
		FROM task AS node,
        task AS parent
		WHERE node.gauche BETWEEN parent.gauche AND parent.droite
        AND node.id = ' . $this->id . ' 
		ORDER BY parent.gauche 
		LIMIT 1')->queryOne();
		
		return $req['deadline'];
	}
	/* Récupérer les sous-tâches d'une tâche (chemin complet) */
	public function getCheminComplet()
	{
		$req = Yii::$app->db->createCommand('
		SELECT node.id
		FROM task AS node,
        task AS parent
		WHERE node.gauche BETWEEN parent.gauche AND parent.droite
        AND parent.id = ' . $this->id . '
		ORDER BY node.gauche
		LIMIT 1,30')->queryAll();
		
		return $req;
	}
	/* Récupérer le niveau de la tâche */
	public function getNiveau()
	{
		$profondeur = Yii::$app->db->createCommand('SELECT node.title, (COUNT(parent.id) - 1) AS depth
							FROM task AS node,
									task AS parent
							WHERE node.gauche BETWEEN parent.gauche AND parent.droite
							AND node.id=' . $this->id . ' 
							GROUP BY node.id
							ORDER BY node.gauche;')->queryOne();
							
							$niveau = $profondeur['depth'];
		return $niveau;
	}
	/* Récupération de la progression pour les tâches mères */
	public function getProgressionTacheMere()
	{
		$progression = Yii::$app->db->createCommand(
								'SELECT node.title, (COUNT(parent.title) - (sub_tree.depth + 1)) as depth,node.percent AS progression
								FROM task AS node,
										task AS parent,
										task AS sub_parent,
										(
												SELECT node.title, (COUNT(parent.title) - 1) AS depth
												FROM task AS node,
														task AS parent
												WHERE node.gauche BETWEEN parent.gauche AND parent.droite
														AND node.id = ' . $this->id . ' 
												GROUP BY node.id
												ORDER BY node.gauche
										)AS sub_tree
								WHERE node.gauche BETWEEN parent.gauche AND parent.droite
										AND node.gauche BETWEEN sub_parent.gauche AND sub_parent.droite
										AND sub_parent.title = sub_tree.title
								GROUP BY node.id
								HAVING depth = 1
								ORDER BY node.gauche')->queryAll();
					$cpt=0;$progTacheMere=0;
					foreach ($progression as $progr) {
							$cpt++;
							$progTacheMere+=$progr['progression'];
						}
					if ($cpt!=0) {
					$progTacheMere/=$cpt;
					return $progTacheMere;
					}
					else return 0;
	}
   
}
