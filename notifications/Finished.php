<?php

namespace humhub\modules\tasks\notifications;

use humhub\modules\notification\components\BaseNotification;

class Finished extends BaseNotification
{

    public $moduleId = 'tasks';
    public $viewName = "finished";

}

?>
