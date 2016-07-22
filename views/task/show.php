<?php

use yii\helpers\Html;
use humhub\modules\tasks\models\Task;
use humhub\modules\comment\models\Comment;

humhub\modules\tasks\Assets::register($this);
?>

<div class="panel panel-default">
    <div class="panel-body">
		
        <div id="open-tasks">
            <?php foreach ($tasks as $task) : ?>
                
				<?php // On récupère la date limite 
				$datelimite=$task->getMotherDeadline();
				$datelimite=strtotime($datelimite);
				$datelimite=date("d M.", $datelimite);
				$progTacheMere=0;
				// On récupère le niveau de la tâche
				$niveau = $task->getNiveau();
				// Marge de 25 px pour chaque niveau 
			    $marge = 25 * $niveau; 
			    // On récupère le nombre de sous-tâches
			    $nbEnfants = $task->getChildrenNumber();
				/* Si la tâche a des sous-tâches, on récupère sa progression en calculant 
				   la moyenne de la progression de ses sous-tâches directes  */
				if($nbEnfants > 0){
				$progTacheMere = $task->getProgressionTacheMere();
				// Si la progression est différente de 0, on modifie le pourcentage de progression dans la base également
				if ($progTacheMere != 0)
				$task->changePercent($progTacheMere);
				}
				if($nbEnfants == 0)
					$prog = $task->percent; 
				else $prog=$progTacheMere;
				if ($prog <= 25)
					$classProg='progress-bar-danger';
				else if ($prog>25 && $prog<= 50)
					$classProg='progress-bar-warning';
				else if ($prog>50 && $prog<= 75)
					$classProg='progress-bar-info';
				else if ($prog>75) 
					$classProg='progress-bar-success';
				else $classProg='progress-bar';
							
                if ($task->status == Task::STATUS_OPEN) : ?>
                    <div class="media task" id="task_<?php echo $task->id; ?>" style="margin-left:<?php echo $marge; ?>px">

                        <?php
                        $currentUserAssigned = false;

                        // Check if current user is assigned to this task
                        foreach ($task->assignedUsers as $au) {
                            if ($au->id == Yii::$app->user->id) {
                                $currentUserAssigned = true;
                                break;
                            }
                        }
                        ?>

						<!-- Bouton pour terminer la tâche -->
						<?php if($niveau==0) 
								$messageTask = 'task'; 
							  else 
							    $messageTask = 'subtask'; ?>
                        <div class="open-check">
                            <?php
                            echo \humhub\widgets\AjaxButton::widget([
                                'label' => '<div class="tasks-check tt pull-left" style="margin-right: 0;" data-toggle="tooltip" data-placement="top" data-original-title="' . Yii::t("TasksModule.widgets_views_entry", "Click, to finish this ".$messageTask."")  . '"><i class="fa fa-square-o task-check"> </i></div>',
                                'tag' => 'a',
                                'ajaxOptions' => [
                                    'dataType' => "json",
                                    'beforeSend' => "completeTask(" . $task->id . ")",
                                    'success' => "function(json) {  $('#wallEntry_'+json.wallEntryId).html(parseHtml(json.output)); }",
                                    'url' => $contentContainer->createUrl('/tasks/task/change-status', array('taskId' => $task->id, 'status' => Task::STATUS_FINISHED)),
                                ],
                                'htmlOptions' => [
                                    'id' => "TaskFinishLink_" . $task->id
                                ]
                            ]);
                            ?>
                        </div>
						<!-- Bouton pour ré-ouvrir la tâche -->
                        <div class="completed-check hidden">
                            <?php
                            echo \humhub\widgets\AjaxButton::widget([
                                'label' => '<div class="tasks-check tt pull-left" style="margin-right: 0;" data-toggle="tooltip" data-placement="top" data-original-title="' . Yii::t("TasksModule.widgets_views_entry", "This task is already done. Click to reopen.") . '"><i class="fa fa-check-square-o task-check"> </i></div>',
                                'tag' => 'a',
                                'ajaxOptions' => [
                                    'dataType' => "json",
                                    'beforeSend' => "reopenTask(" . $task->id . ")",
                                    'success' => "function(json) {  $('#wallEntry_'+json.wallEntryId).html(parseHtml(json.output));}",
                                    'url' => $contentContainer->createUrl('/tasks/task/change-status', array('taskId' => $task->id, 'status' => Task::STATUS_OPEN)),
                                ],
                                'htmlOptions' => [
                                    'id' => "TaskOpenLink_" . $task->id
                                ]
                            ]);
                            ?>
                        </div>
						
						<!-- titre et heading du bloc tâche -->
                        <div class="media-heading">
                            <span class="task-title pull-left"><?php echo $task->title; ?></span>
							
                            <?php if ($task->hasDeadline()) : ?>
                                <?php
                                $timestamp = strtotime($task->deadline);
                                $date=new DateTime(date("d.m.yy", $timestamp));
                                $ajd=new DateTime(date("d.m.yy", time()));
                                $class = "label label-success";if ($date <= $ajd->add(new DateInterval('P1W'))) {
									$class = "label label-warning";
								}
                                
                                if (date("d.m.yy", $timestamp) <= date("d.m.yy", time())) {
                                    $class = "label label-danger";
                                }
                                ?>
                                <span class="<?php echo $class; ?>"><?php echo '<i class="fa fa-clock-o"></i>  ' . date("d. M", $timestamp); ?></span>
                            <?php endif; ?>
							 
							<?php // Bouton pour afficher la description
								if($task->description != null) :
							
								  $message='Afficher/Masquer description';  ?>
							<a data-toggle="collapse" class="tt"  onclick="changeOeil(<?php echo $task->id; ?>)"
                                   href="#bloc_description_<?php echo $task->id; ?>"
                                   onclick="$('#bloc_description_<?php echo $task->id; ?>').show();return false;"
                                   aria-expanded="false" data-toggle="tooltip"
                                   data-placement="top" data-original-title="<?php echo $message; ?>"
                                   ><i id="btnOeil_<?php echo $task->id;?>"
                                        class="fa fa-eye"></i> 
                                </a>
                            <?php endif; ?>
                            <?php if($task->percent != 0 || $prog != 0) :
							
								  $message='Afficher/Masquer progression';  ?>
							<a data-toggle="collapse" class="tt"  onclick="affichPercent(<?php echo $task->id; ?>);"
                                   href="#progress_<?php echo $task->id; ?>"
                                   onclick="$('#progress_<?php echo $task->id; ?>').show();return false;"
                                   aria-expanded="false" data-toggle="tooltip"
                                   data-placement="top" data-original-title="<?php echo $message; ?>"
                                   ><i class="fa fa-percent" id="percent_<?php echo $task->id; ?>"></i> 
                                </a>
                            <?php endif; ?>
                            <div class="task-controls end pull-right">
								<!-- Bouton pour éditer le message -->
                                <a href="<?php echo $contentContainer->createUrl('edit', ['id' => $task->id,'datelimite'=>$datelimite]); ?>"
                                   class="tt"
                                   data-target="#globalModal" data-toggle="tooltip"
                                   data-placement="top" data-original-title="Modifier la <?php if($niveau>0)echo 'sous-';if($niveau>1)echo 'sous-';?>tâche"><i class="fa fa-pencil"></i></a>

								
                                <?php // Bouton suppression tâche
                                echo humhub\widgets\ModalConfirm::widget(array(
                                    'uniqueID' => 'modal_delete_task_' . $task->id,
                                    'linkOutput' => 'a',
                                    'title' => Yii::t('TasksModule.views_task_show', '<strong>Confirm</strong> deleting'),
                                    'message' => Yii::t('TasksModule.views_task_show', 'Do you really want to delete this task?'),
                                    'buttonTrue' => Yii::t('TasksModule.views_task_show', 'Delete'),
                                    'buttonFalse' => Yii::t('TasksModule.views_task_show', 'Cancel'),
                                    'linkContent' => '<i class="fa fa-times-circle-o colorDanger"></i>',
                                    'linkHref' => $contentContainer->createUrl('delete', array('id' => $task->id)),
                                    'confirmJS' => "$('#task_" . $task->id . "').fadeOut('fast')",
                                ));
                                ?>

                            </div>
							<!-- Bouton pour afficher les commentaires -->
                            <div class="task-controls pull-right">
							<?php $count = Comment::GetCommentCount($task->className(), $task->id);
							if ($count==0) $classCount='fa fa-commenting-o'; 
							else if($count==1) $classCount='fa fa-comment-o';
						    else $classCount='fa fa-comments-o'; ?>
                                <a data-toggle="collapse"
                                   href="#task-comment-<?php echo $task->id; ?>"
                                   onclick="$('#comment_humhubmodulestasksmodelsTask_<?php echo $task->id; ?>').show();return false;"
                                   aria-expanded="false"
                                   aria-controls="collapseTaskComments"><i
                                        class="<?php echo $classCount; ?>"></i> <?php echo $count ; ?>
                                </a>

                            </div>

							<!-- Bloc utilisateurs assignés -->
                            <div class="task-controls assigned-users pull-right" style="display: inline;">
                                <!-- Show assigned user -->
                                <?php foreach ($task->assignedUsers as $user): ?>
                                    <a href="<?php echo $user->getUrl(); ?>" id="user_<?php echo $task->id; ?>">
                                        <img src="<?php echo $user->getProfileImage()->getUrl(); ?>" class="img-rounded tt"
                                             height="24" width="24" alt="24x24"
                                             style="width: 24px; height: 24px;" data-toggle="tooltip" data-placement="top"
                                             title=""
                                             data-original-title="<?php echo Html::encode($user->displayName); ?>">
                                    </a>

                                <?php endforeach; ?>
                            </div>
                            <!-- Ajout du bouton sous-tâche -->
                            <?php if ($niveau==0) 
										$messageT = 'Ajouter sous-tâche'; 
								  else  $messageT = 'Ajouter sous-sous-tâche'; ?>
							<div class="task-controls pull-right">

								<a href="<?php echo $contentContainer->createUrl('edit', ['id' => null,'parent' => $task->id,'datelimite'=>$datelimite]); ?>"
                                   class="tt"
                                   data-target="#globalModal" data-toggle="tooltip"
                                   data-placement="top" data-original-title="<?php echo $messageT; ?>"><i class="fa fa-plus"></i></a>
							</div>
							<!-- Ajout du bouton 'Priorité' -->
							<div class="task-controls pull-right">
								<?php if($task->priority == 5) {$classPriority = 'rouge';} else if($task->priority == 3){$classPriority = 'yellow';}else{$classPriority = 'green';}
                            echo \humhub\widgets\AjaxButton::widget([
                                'label' => '<i class="priority tt fa fa-star ' . $classPriority . '"
											   id="priority_' . $task->id .'" 
                                               data-toggle="tooltip" data-placement="top" data-original-title="Changer la priorité" 
                                               onclick="changePriority(' . $task->priority . ',' . $task->id . ')"></i>',
                                'tag' => 'a',
                                'ajaxOptions' => [
                                    'url' => $contentContainer->createUrl('/tasks/task/change-priority', array('taskId' => $task->id, 'priority' => $task->priority)),
                                ],
                                'htmlOptions' => [
                                    'id' => "TaskChangePriority_" . $task->id
                                ]
                            ]);
                            ?>
							
							</div>
							
                            <div class="clearfix"></div>

                        </div>
                        <!-- Bloc description (masqué par défaut, visible en cliquant sur l'oeil) -->
						<div class="media-body description">
						<!-- Barre de progression -->
						
							
							<div id="progress_<?php echo $task->id;  ?>" class="collapse">
							<div class="progress" style="height:15px;line-height:15px;">
							
							  <div class="<?php echo $classProg; ?>" role="progressbar" aria-valuenow="<?php echo $prog; ?>"
							  aria-valuemin="0" aria-valuemax="100" style="width:<?php echo $prog; ?>%">
								<div style="text-align:center"><?php echo number_format($prog,0); ?>%</div>
							  </div>
							</div>
							</div>
							<div class="collapse" id="bloc_description_<?php echo $task->id; ?>">
							
							
							
								<?php echo humhub\widgets\MarkdownView::widget(array('markdown' => $task->description)); ?>
							</div>
						</div>
                        <div class="wall-entry collapse" id="task-comment-<?php echo $task->id; ?>">
                            <div class="wall-entry-controls">
                                <?php //echo \humhub\modules\comment\widgets\CommentLink::widget(array('object' => $task)); ?>
                            </div>
                            <?php echo \humhub\modules\comment\widgets\Comments::widget(array('object' => $task)); ?>
                        </div>

                        <script type="text/javascript">
                            $('#task-comment-<?php echo $task->id; ?>').on('shown.bs.collapse', function () {
                                $('#newCommentForm_humhubmodulestasksmodelsTask_<?php echo $task->id; ?>_contenteditable').focus();
                            })
                        </script>


                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (count($tasks) == 0 || count($tasks) == $completedTaskCount) : ?>
                <em><?php echo Yii::t('TasksModule.views_task_show', 'No open tasks...'); ?></em>
            <?php endif; ?>
        </div>


        <br>


        <?php if ($canCreateNewTasks): ?>
            <a href="<?php echo $contentContainer->createUrl('edit'); ?>" class="btn btn-primary"
               data-target="#globalModal"><i
                    class="fa fa-plus"></i> <?php echo Yii::t('TasksModule.views_task_show', 'Add Task'); ?></a>
            <?php endif; ?>


        <a data-toggle="collapse" id="completed-task-link" href="#completed-tasks" class="show-completed-tasks-link"
           style="display: none;"><i
                class="fa fa-check"></i>
        </a>

        <div class="collapse <?php if (Yii::$app->request->get('completed') != null) : ?>in<?php endif; ?>"
             id="completed-tasks">
            <br>
            <br>
            <?php foreach ($tasks as $task) : ?>

                <?php if ($task->status == Task::STATUS_FINISHED) : ?>
                    <div class="media task" id="task_<?php echo $task->id; ?>">

                        <?php
                        $currentUserAssigned = false;

                        // Check if current user is assigned to this task
                        foreach ($task->assignedUsers as $au) {
                            if ($au->id == Yii::$app->user->id) {
                                $currentUserAssigned = true;
                                break;
                            }
                        }
                        ?>


                        <div class="open-check hidden">
                            <?php
                            echo \humhub\widgets\AjaxButton::widget([
                                'label' => '<div class="tasks-check tt pull-left" style="margin-right: 0;" data-toggle="tooltip" data-placement="top" data-original-title="' . Yii::t("TasksModule.widgets_views_entry", "Click, to finish this task") . '"><i class="fa fa-square-o task-check"> </i></div>',
                                'tag' => 'a',
                                'ajaxOptions' => [
                                    'dataType' => "json",
                                    'beforeSend' => "completeTask(" . $task->id . ")",
                                    'success' => "function(json) {  $('#wallEntry_'+json.wallEntryId).html(parseHtml(json.output)); }",
                                    'url' => $contentContainer->createUrl('/tasks/task/change-status', array('taskId' => $task->id, 'status' => Task::STATUS_FINISHED)),
                                ],
                                'htmlOptions' => [
                                    'id' => "TaskFinishLink_" . $task->id
                                ]
                            ]);
                            ?>
                        </div>

                        <div class="completed-check">
                            <?php
                            echo \humhub\widgets\AjaxButton::widget([
                                'label' => '<div class="tasks-check tt pull-left" style="margin-right: 0;" data-toggle="tooltip" data-placement="top" data-original-title="' . Yii::t("TasksModule.widgets_views_entry", "This task is already done. Click to reopen.") . '"><i class="fa fa-check-square-o task-check"> </i></div>',
                                'tag' => 'a',
                                'ajaxOptions' => [
                                    'dataType' => "json",
                                    'beforeSend' => "reopenTask(" . $task->id . ")",
                                    'success' => "function(json) {  $('#wallEntry_'+json.wallEntryId).html(parseHtml(json.output));}",
                                    'url' => $contentContainer->createUrl('/tasks/task/change-status', array('taskId' => $task->id, 'status' => Task::STATUS_OPEN)),
                                ],
                                'htmlOptions' => [
                                    'id' => "TaskOpenLink_" . $task->id
                                ]
                            ]);
                            ?>
                        </div>
						

                        <div class="media-body">
                            <span class="task-title task-completed pull-left"><?php echo $task->title; ?></span>

                            <?php if ($task->hasDeadline()) : ?>
                                <?php
                                $timestamp = strtotime($task->deadline);
                                $class = "label label-default";
                                
                                if ($date <= $ajd->add(new DateInterval('P1W'))) {
									$class = "label label-warning";
								}
                                if (date("d.m.yy", $timestamp) <= date("d.m.yy", time())) {
                                    $class = "label label-danger";
								}
                                
                                
                                ?>
                                <span
                                    class="<?php echo $class; ?> task-completed-controls"><?php echo '<i class="fa fa-clock-o"></i>' . date("d. M", $timestamp); ?></span>
                                <?php endif; ?>


                            <div class="task-controls end pull-right">

                                <a href="<?php echo $contentContainer->createUrl('edit', ['id' => $task->id,'datelimite'=>$datelimite]); ?>"
                                   class="tt"
                                   data-target="#globalModal" data-toggle="tooltip"
                                   data-placement="top" data-original-title="Modifier la <?php if($niveau>0) echo 'sous-'; ?>tâche"><i class="fa fa-pencil"></i></a>


                                <?php
                                echo humhub\widgets\ModalConfirm::widget(array(
                                    'uniqueID' => 'modal_delete_task_' . $task->id,
                                    'linkOutput' => 'a',
                                    'title' => Yii::t('TasksModule.views_task_show', '<strong>Confirm</strong> deleting'),
                                    'message' => Yii::t('TasksModule.views_task_show', 'Do you really want to delete this task?'),
                                    'buttonTrue' => Yii::t('TasksModule.views_task_show', 'Delete'),
                                    'buttonFalse' => Yii::t('TasksModule.views_task_show', 'Cancel'),
                                    'linkContent' => '<i class="fa fa-times-circle-o colorDanger"></i>',
                                    'linkHref' => $contentContainer->createUrl('delete', array('id' => $task->id)),
                                    'confirmJS' => "$('#task_" . $task->id . "').fadeOut('fast')",
                                ));
                                ?>

                            </div>

                            <div class="task-controls pull-right">

                                <a data-toggle="collapse"
                                   href="#task-comment-<?php echo $task->id; ?>"
                                   onclick="$('#comment_humhubmodulestasksmodelsTask_<?php echo $task->id; ?>').show();return false;"
                                   aria-expanded="false"
                                   aria-controls="collapseTaskComments"><i
                                        class="<?php echo $classCount; ?>"></i> <?php echo $count; ?>
                                </a>

                            </div>


                            <div class="task-controls pull-right assigned-users task-completed-controls"
                                 style="display: inline;">
                                <!-- Show assigned user -->
                                <?php foreach ($task->assignedUsers as $user): ?>
                                    <a href="<?php echo $user->getUrl(); ?>" id="user_<?php echo $task->id; ?>">
                                        <img src="<?php echo $user->getProfileImage()->getUrl(); ?>" class="img-rounded tt"
                                             height="24" width="24" alt="24x24"
                                             style="width: 24px; height: 24px;" data-toggle="tooltip" data-placement="top"
                                             title=""
                                             data-original-title="<?php echo Html::encode($user->displayName); ?>">
                                    </a>

                                <?php endforeach; ?>
                            </div>

                            <div class="clearfix"></div>

                        </div>

                        <div class="wall-entry collapse " id="task-comment-<?php echo $task->id; ?>">
                            <div class="wall-entry-controls">
                                <?php //echo \humhub\modules\comment\widgets\CommentLink::widget(array('object' => $task)); ?>
                            </div>
                            <?php echo \humhub\modules\comment\widgets\Comments::widget(array('object' => $task)); ?>
                        </div>

                        <script type="text/javascript">
                            $('#task-comment-<?php echo $task->id; ?>').on('shown.bs.collapse', function () {
                                $('#newCommentForm_humhubmodulestasksmodelsTask_<?php echo $task->id; ?>_contenteditable').focus();
                            })
                        </script>


                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script type="text/javascript">

    var _id = <?php echo (int) Yii::$app->request->get('id'); ?>;
    var _completedTaskCount = <?php echo $completedTaskCount; ?>;
    var _completedTaskButtonText = "<?php echo Yii::t('TasksModule.views_task_show', 'completed tasks'); ?>";
    if (_id > 0) {
        $('#task_' + _id).addClass('highlight');
        $('#task_' + _id).animate({
            backgroundColor: "#fff"
        }, 2000);
    }


    function completeTask(id) {
        $('#task_' + id + ' .open-check').addClass('hidden');
        $('#task_' + id + ' .completed-check').removeClass('hidden');
        $('#task_' + id + ' .task-title').addClass('task-completed');
        $('#task_' + id + ' .assigned-users').addClass('task-completed-controls');
        $('#task_' + id + ' .label').addClass('task-completed-controls');
        $('#task_' + id).appendTo('#completed-tasks');
        _completedTaskCount++;
        handleCompletedTasks();

    }

    function reopenTask(id) {
        $('#task_' + id + ' .open-check').removeClass('hidden');
        $('#task_' + id + ' .completed-check').addClass('hidden');
        $('#task_' + id + ' .task-title').removeClass('task-completed');
        $('#task_' + id + ' .assigned-users').removeClass('task-completed-controls');
        $('#task_' + id + ' .label').removeClass('task-completed-controls');
        $('#task_' + id).appendTo('#open-tasks');
        _completedTaskCount--;
        handleCompletedTasks();
    }

    function handleCompletedTasks() {
        $('#completed-task-link').html('<i class="fa fa-check"></i> ' + _completedTaskCount + ' ' + _completedTaskButtonText);

        if (_completedTaskCount != 0) {
            $('#completed-task-link').fadeIn('fast');
        } else {
            $('#completed-task-link').fadeOut('fast');
            $('#completed-tasks').removeClass('in');
        }

    }
    // AJOUTS
    // Fonction pour afficher la barre sur l'oeil
	function changeOeil(id) {
			$('#btnOeil_' + id).toggleClass('fa-eye-slash fa-eye rouge');
	}
	// Fonction pour faire passer le signe '%' en rouge
	function affichPercent(id) {
			$('#percent_' + id).toggleClass('rouge');
	}
	// Fonction pour remonter la tâche d'un rang : 
	/*function remonterTache(id, parent) {
		
		$('#task_' + id).insertBefore('#task_' + parent);
		}*/
	// Fonction pour cacher les sous-tâches
	function changePriority(priority, id) {
			
			if (priority == 1) {
			$('#priority_' + id).removeClass('green');
			$('#priority_' + id).addClass('yellow');
			}
			else if (priority == 3){
			$('#priority_' + id).removeClass('yellow');
			$('#priority_' + id).addClass('rouge');
			}
			else {
			$('#priority_' + id).removeClass('rouge');
			$('#priority_' + id).addClass('green');
			}
		}
    $(document).ready(function () {
        handleCompletedTasks();
    });

</script>




