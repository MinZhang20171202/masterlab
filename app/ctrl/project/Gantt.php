<?php
/**
 * Created by PhpStorm.
 */

namespace main\app\ctrl\project;

use main\app\classes\IssueFilterLogic;
use main\app\classes\ConfigLogic;
use main\app\classes\UserAuth;
use main\app\classes\PermissionLogic;
use main\app\classes\UserLogic;
use main\app\ctrl\BaseUserCtrl;
use main\app\model\agile\SprintModel;
use main\app\classes\RewriteUrl;
use main\app\classes\ProjectGantt;
use main\app\model\issue\IssueModel;
use main\app\model\issue\IssueStatusModel;
use main\app\model\project\ProjectRoleModel;

/**
 * 甘特图
 */
class Gantt extends BaseUserCtrl
{

    /**
     * Stat constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        parent::addGVar('top_menu_active', 'project');
        parent::addGVar('sub_nav_active', 'project');
    }

    public function pageComputeLevel()
    {
        $class = new ProjectGantt();
        $class->batchUpdateGanttLevel();
        echo 'ok';
    }

    /**
     * @throws \Exception
     */
    public function pageIndex()
    {
        $data = [];
        $data['title'] = '项目甘特图';
        $data['nav_links_active'] = 'gantt';
        $data = RewriteUrl::setProjectData($data);
        // 权限判断
        if (!empty($data['project_id'])) {
            if (!$this->isAdmin && !PermissionLogic::checkUserHaveProjectItem(UserAuth::getId(), $data['project_id'])) {
                $this->warn('提 示', '您没有权限访问该项目,请联系管理员申请加入该项目');
                die;
            }
        }
        ConfigLogic::getAllConfigs($data);
        $this->render('gitlab/project/gantt/gantt_project.php', $data);
    }

    /**
     * 获取项目的统计数据
     * @throws \Exception
     */
    public function fetchProjectIssues()
    {
        $projectId = null;
        if (isset($_GET['_target'][3])) {
            $projectId = (int)$_GET['_target'][3];
        }
        if (isset($_GET['project_id'])) {
            $projectId = (int)$_GET['project_id'];
        }
        if (empty($projectId)) {
            $this->ajaxFailed('参数错误', '项目id不能为空');
        }
        $data['current_uid'] = UserAuth::getId();

        $model = new ProjectRoleModel();
        $data['project_roles'] = $model->getsByProject($projectId);
        $roles = [];
        foreach ($data['project_roles'] as $project_role) {
            $roles[] = ['id' => $project_role['id'], 'name' => $project_role['name']];
        }

        $userLogic = new UserLogic();
        $projectUsers = $userLogic->getUsersAndRoleByProjectId($projectId);
        $resources = [];
        foreach ($projectUsers as &$user) {
            $user = UserLogic::format($user);
            $resources[] = ['id' => $user['uid'], 'name' => $user['display_name']];
        }
        $data['project_users'] = $projectUsers;

        $class = new ProjectGantt();
        $data['tasks'] = $class->getIssuesGroupBySprint($projectId);
        $userLogic = new UserLogic();
        $users = $userLogic->getAllNormalUser();
        foreach ($data['tasks'] as &$task) {
            $assigs = [];
            if (isset($users[$task['assigs']]['display_name'])) {
                $tmp = [];
                $tmp['id'] = $task['assigs'];
                $tmp['name'] = @$users[$task['assigs']]['display_name'];
                $tmp['resourceId'] = $task['assigs'];
                $tmp['roleId'] = '';
                $assigs[] = $tmp;
            }
            $task['assigs'] = $assigs;
        }
        unset($users);
        $data['selectedRow'] = 2;
        $data['deletedTaskIds'] = [];
        $data['resources'] = $resources;
        $data['roles'] = $roles;
        $data['canWrite'] = true;
        $data['canDelete'] = true;
        $data['canWriteOnParent'] = true;
        $data['canAdd'] = true;
        $this->ajaxSuccess('ok', $data);
    }

    public function moveUpIssue()
    {
        $currentId = null;
        if (isset($_POST['current_id'])) {
            $currentId = (int)$_POST['current_id'];
        }
        $newId = null;
        if (isset($_POST['new_id'])) {
            $newId = (int)$_POST['new_id'];
        }
        if (!$currentId || !$newId) {
            $this->ajaxFailed('参数错误', $_POST);
        }
        $issueModel = new IssueModel();
        $currentIsuse = $issueModel->getById($currentId);
        $newIssue = $issueModel->getById($newId);
        if (!isset($currentIsuse['gant_proj_sprint_weight']) || !isset($newIssue['gant_proj_sprint_weight'])) {
            $this->ajaxFailed('参数错误,找不到事项信息', $_POST);
        }

        $currentWeight = (int)$currentIsuse['gant_proj_sprint_weight'];
        $newWeight = (int)$newIssue['gant_proj_sprint_weight'];
        if ($currentWeight == $newWeight) {
            $currentWeight++;
        } else {
            $tmp = $newWeight;
            $newWeight = $currentWeight;
            $currentWeight = $tmp;
        }
        $currentInfo = ['gant_proj_sprint_weight' => $currentWeight];
        $newInfo = ['gant_proj_sprint_weight' => $newWeight];
        $issueModel->updateItemById($currentId, $currentInfo);
        $issueModel->updateItemById($newId, $newInfo);

        $this->ajaxSuccess('更新成功', [$currentId => $currentInfo, $newId => $newInfo]);
    }

    public function moveDownIssue()
    {
        $currentId = null;
        if (isset($_POST['current_id'])) {
            $currentId = (int)$_POST['current_id'];
        }
        $newId = null;
        if (isset($_POST['new_id'])) {
            $newId = (int)$_POST['new_id'];
        }
        if (!$currentId || !$newId) {
            $this->ajaxFailed('参数错误', $_POST);
        }
        $issueModel = new IssueModel();
        $currentIsuse = $issueModel->getById($currentId);
        $newIssue = $issueModel->getById($newId);
        if (!isset($currentIsuse['gant_proj_sprint_weight']) || !isset($newIssue['gant_proj_sprint_weight'])) {
            $this->ajaxFailed('参数错误,找不到事项信息', $_POST);
        }

        $currentWeight = (int)$currentIsuse['gant_proj_sprint_weight'];
        $newWeight = (int)$newIssue['gant_proj_sprint_weight'];
        if ($currentWeight == $newWeight) {
            $newWeight++;
        } else {
            $tmp = $newWeight;
            $newWeight = $currentWeight;
            $currentWeight = $tmp;
        }
        $currentInfo = ['gant_proj_sprint_weight' => $currentWeight];
        $newInfo = ['gant_proj_sprint_weight' => $newWeight];
        $issueModel->updateItemById($currentId, $currentInfo);
        $issueModel->updateItemById($newId, $newInfo);

        $this->ajaxSuccess('更新成功', [$currentId => $currentInfo, $newId => $newInfo]);
    }

    public function outdent()
    {
        $issueId = null;
        if (isset($_POST['issue_id'])) {
            $issueId = (int)$_POST['issue_id'];
        }

        $masterId = '0';
        if (isset($_POST['old_master_id'])) {
            $masterId = (int)$_POST['old_master_id'];
        }

        $nextId = '0';
        if (isset($_POST['next_id'])) {
            $nextId = (int)$_POST['next_id'];
        }

        $children = [];
        if (isset($_POST['children'])) {
            $children = $_POST['children'];
        }

        if (!$issueId) {
            $this->ajaxFailed('参数错误', $_POST);
        }
        $issueModel = new IssueModel();
        $issue = $issueModel->getById($issueId);
        $masterWeight = 0;
        $nextWeight = 0;
        $level = 0;
        $nextMasterId = 0;
        if ($masterId != '0') {
            $masterIssue = $issueModel->getById($masterId);
            if (!empty($masterIssue)) {
                $masterId = $masterIssue['master_id'];
                $level = $masterIssue['level '];
                $masterWeight = $masterIssue['gant_proj_sprint_weight'];
            }
        }
        if ($nextId != '0') {
            $nextIssue = $issueModel->getById($nextId);
            if (!empty($nextIssue)) {
                $nextWeight = $nextIssue['gant_proj_sprint_weight'];
                $nextMasterId = (int)$nextIssue['master_id'];
            }
        }
        $weight = round($masterWeight/2);

        $currentInfo = [];
        $currentInfo['level'] = $level;
        $currentInfo['master_id'] = $masterId;
        $currentInfo['gant_proj_sprint_weight'] = $weight;
        $issueModel->updateItemById($issueId, $currentInfo);

        if (!empty($children)) {
            foreach ($children as $childId) {
                $issueModel->updateItemById($childId, ['master_id' => $issueId]);
            }
            $issueModel->inc('have_children', $issueId, 'id');
        }
        if (!empty($issue['master_id']) && $issue['master_id'] != '0') {
            $issueModel->dec('have_children', $issue['master_id'], 'id');
        }
        $this->ajaxSuccess('更新成功', []);
    }


    /**
     * 计算百分比
     * @param $rows
     * @param $count
     */
    private function percent(&$rows, $count)
    {
        foreach ($rows as &$row) {
            if ($count <= 0) {
                $row['percent'] = 0;
            } else {
                $row['percent'] = floor(intval($row['count']) / $count * 100);
            }
        }
    }
}
