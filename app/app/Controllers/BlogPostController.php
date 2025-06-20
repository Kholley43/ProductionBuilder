<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

class BlogPostController extends ResourceController
{
    protected $modelName = 'App\\Models\\BlogPost';
    protected $format    = 'json';

    /**
     * GET /blogposts
     */
    public function index()
    {
        return $this->respond($this->model->findAll());
    }

    /**
     * GET /blogposts/{id}
     */
    public function show($id = null)
    {
        $item = $this->model->find($id);
        return $item ? $this->respond($item) : $this->failNotFound();
    }

    /**
     * POST /blogposts
     */
    public function create()
    {
        $data = $this->request->getJSON(true);
        if (! $this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }
        return $this->respondCreated($data);
    }

    /**
     * PUT/PATCH /blogposts/{id}
     */
    public function update($id = null)
    {
        $data = $this->request->getJSON(true);
        if (! $this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }
        return $this->respond($this->model->find($id));
    }

    /**
     * DELETE /blogposts/{id}
     */
    public function delete($id = null)
    {
        return $this->model->delete($id) ? $this->respondDeleted() : $this->failNotFound();
    }
}