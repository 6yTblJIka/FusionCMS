<?php

use CodeIgniter\Events\Events;
use MX\MX_Controller;

/**
 * Admin changelog Controller Class
 * @property changelog_model $changelog_model changelog_model Class
 */
class Admin extends MX_Controller
{
    public function __construct()
    {
        // Make sure to load the administrator library!
        $this->load->library('administrator');
        $this->load->model('changelog_model');

        parent::__construct();

        requirePermission("canViewAdmin");
    }

    public function index()
    {
        // Change the title
        $this->administrator->setTitle("Changelog");

        $changes = $this->changelog_model->getChangelog();

        if ($changes) {
            foreach ($changes as $key => $value) {
                if (strlen($value['changelog']) > 30) {
                    $changes[$key]['changelog'] = mb_substr($value['changelog'], 0, 30) . '...';
                }
            }
        }

        // Prepare my data
        $data = array(
            'url' => $this->template->page_url,
            'changes' => $changes,
            'categories' => $this->changelog_model->getCategories()
        );

        // Load my view
        $output = $this->template->loadPage("admin.tpl", $data);

        // Put my view in the main box with a headline
        $content = $this->administrator->box('Changelog', $output);

        // Output my content. The method accepts the same arguments as template->view
        $this->administrator->view($content, false, "modules/changelog/js/admin_changelog.js");
    }

    public function create()
    {
        requirePermission("canAddChange");

        $name = $this->input->post("typeName");

        $id = $this->changelog_model->addCategory($name);

        // Add log
        $this->dblogger->createLog("admin", "add", "Created category", ['Category' => $name]);

        Events::trigger('onAddCategoryChangelog', $id, $name);
    }

    public function addChange($id)
    {
        requirePermission("canAddChange");

        $data['changelog'] = $this->input->post("change_message");
        $data['author'] = $this->user->getNickname();
        $data['type'] = $id;
        $data['time'] = time();

        $data['id'] = $this->changelog_model->add($data);

        $data['date'] = date("Y/m/d");

        // Add log
        $this->dblogger->createLog("admin", "add", 'Created change', ['Change' => $data['changelog'] . ' (' . $id . ')']);

        Events::trigger('onAddChangelog', $data['id'], $data['changelog'], $data['type']);

        die(json_encode($data));
    }

    public function edit($id = false)
    {
        requirePermission("canEditChange");

        if (!is_numeric($id) || !$id) {
            die();
        }

        $change = $this->changelog_model->getChange($id);

        if (!$change) {
            show_error("There is no change with ID " . $id, 400);

            die();
        }

        // Change the title
        $this->administrator->setTitle("Change #" . $id);

        // Prepare my data
        $data = array(
            'url' => $this->template->page_url,
            'changelog' => $change
        );

        // Load my view
        $output = $this->template->loadPage("admin_edit_changelog.tpl", $data);

        // Put my view in the main box with a headline
        $content = $this->administrator->box('<a href="' . $this->template->page_url . 'changelog/admin">Changelog</a> &rarr; Change #' . $id, $output);

        // Output my content. The method accepts the same arguments as template->view
        $this->administrator->view($content, false, "modules/changelog/js/admin_changelog.js");
    }

    public function delete($id = false)
    {
        requirePermission("canRemoveChange");

        if (!$id || !is_numeric($id)) {
            die();
        }

        $this->changelog_model->deleteChange($id);

        // Add log
        $this->dblogger->createLog("admin", "delete", "Deleted change", ['ID' => $id]);

        Events::trigger('onAddChangelog', $id);
    }

    public function deleteCategory($id = false)
    {
        // Check for the permission
        requirePermission("canRemoveCategory");

        if (!$id || !is_numeric($id)) {
            die();
        }

        $this->changelog_model->deleteCategory($id);

        // Add log
        $this->dblogger->createLog("admin", "delete", "Deleted category", ['ID' => $id]);

        Events::trigger('onDeleteCategoryChangelog', $id);
    }

    public function save($id = false)
    {
        requirePermission("canEditChange");

        if (!$id || !is_numeric($id)) {
            die();
        }

        $data["changelog"] = $this->input->post("text");

        $this->changelog_model->edit($id, $data);

        // Add log
		$this->dblogger->createLog("admin", "edit", "Edited change", ['ID' => $id]);

        Events::trigger('onEditChangelog', $id, $data['changelog']);
    }

    public function saveCategory($id = false)
    {
        requirePermission("canEditCategory");

        if (!$id || !is_numeric($id)) {
            die();
        }

        $data['typeName'] = $this->input->post('typeName');

        $this->changelog_model->saveCategory($id, $data);

        // Add log
        $this->dblogger->createLog("admin", "edit", "Edited category", ['ID' => $id]);

        Events::trigger('onSaveCategoryChangelog', $id, $data['typeName']);
    }
}
