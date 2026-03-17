<?php

namespace Model;

trait Model
{
    use \Model\Database;

    protected $limit        = 70;
    protected $offset       = 0;
    protected $order_type   = "desc";
    protected $order_column = "id";
    public $errors          = [];

    public function paginate(
        array $where = [],
        int $page = 1,
        int $perPage = 20,
        string $orderBy = 'id',
        string $orderDir = 'desc',
        ?array $allowedOrderColumns = null
    ): array
    {
        $page = $page < 1 ? 1 : $page;
        $perPage = $perPage < 1 ? 1 : $perPage;
        $perPage = $perPage > 100 ? 100 : $perPage;

        [$orderBy, $orderDir] = $this->sanitizePaginationOrder($orderBy, $orderDir, $allowedOrderColumns);
        [$whereSql, $params] = $this->buildPaginateWhere($where);

        $offset = ($page - 1) * $perPage;

        $countQuery = "select count(*) as total from $this->table" . $whereSql;
        $countRows = $this->query($countQuery, $params);
        $total = (int)($countRows[0]->total ?? 0);

        $dataQuery = "select * from $this->table" . $whereSql . " order by {$orderBy} {$orderDir} limit {$perPage} offset {$offset}";
        $items = $this->query($dataQuery, $params);
        if (!is_array($items))
        {
            $items = [];
        }

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $totalPages > 0 ? $page < $totalPages : false,
                'has_prev' => $page > 1,
            ],
        ];
    }

    public function all()
    {

        $query = "select * from $this->table limit $this->limit offset $this->offset";

        return $this->query($query);
    }

    public function where(array $where_array = [], array $where_not_array = [], array $greater_than_array = []): array|bool
    {

        $query = "select * from $this->table where ";

        if (!empty($where_array))
        {
            foreach ($where_array as $key => $value)
            {
                $query .= $key . "= :" . $key . " && ";
            }
        }

        if (!empty($where_not_array))
        {
            foreach ($where_not_array as $key => $value)
            {
                $query .= $key . "!= :" . $key . " && ";
            }
        }

        if (!empty($greater_than_array))
        {
            foreach ($greater_than_array as $key => $value)
            {
                $query .= $key . "> :" . $key . " && ";
            }
        }

        $query = trim($query, " && ");
        $query .= " order by $this->order_column $this->order_type limit $this->limit offset $this->offset";

        $data = array_merge($where_array, $where_not_array, $greater_than_array);
        // dd($data);
        return $this->query($query, $data);
    }

    // public function where($data, $data_not = [])
    // {
    //     $keys = array_keys($data);
    //     $keys_not = array_keys($data_not);
    //     $query = "select * from $this->table where ";
    //     foreach ($keys as $key)
    //     {
    //         $query .= $key . "= :" . $key . " && ";
    //     }
    //     foreach ($keys_not as $key)
    //     {
    //         $query .= $key . "!= :" . $key . " && ";
    //     }

    //     $query = trim($query, " && ");
    //     $query .= " order by $this->order_column $this->order_type limit $this->limit offset $this->offset";
    //     $data = array_merge($data, $data_not);
    //     return $this->query($query, $data);
    // }


    public function first($data, $data_not = [])
    {
        $keys = array_keys($data);
        $keys_not = array_keys($data_not);
        $query = "select * from $this->table where ";
        foreach ($keys as $key)
        {
            $query .= $key . "= :" . $key . " && ";
        }
        foreach ($keys_not as $key)
        {
            $query .= $key . "!= :" . $key . " && ";
        }

        $query = trim($query, " && ");
        $query .= " limit $this->limit offset $this->offset";
        $data = array_merge($data, $data_not);
        $this->query($query, $data);

        $result = $this->query($query, $data);
        if ($result)
            return $result[0];

        return false;
    }

    public function between($dataGreater = [], $dataLess = [])
    {
        $keysGreater = array_keys($dataGreater);
        $keysLess = array_keys($dataLess);
        $query = "select * from $this->table where ";
        foreach ($keysGreater as $key)
        {
            $query .= $key . "> :" . $key . " && ";
        }
        foreach ($keysLess as $key)
        {
            $query .= $key . "< :" . $key . " && ";
        }

        $query = trim($query, " && ");
        $query .= " limit $this->limit offset $this->offset";
        $data = array_merge($dataLess, $dataGreater);
        $this->query($query, $data);

        $result = $this->query($query, $data);
        if ($result)
            return $result;

        return false;
    }

    public function insert($data)
    {
        // ** remove unwanted data **/
        if (!empty($this->allowedColumns))
        {
            foreach ($data as $key => $value)
            {
                if (!in_array($key, $this->allowedColumns))
                {
                    unset($data[$key]);
                }
            }
        }
        $keys = array_keys($data);
        $query = "insert into $this->table (" . implode(",", $keys) . " ) values (:" . implode(",:", $keys) . ")";
        $this->query($query, $data);

        return false;
    }

    public function update($id, $data, $id_column = 'id')
    {
        // ** remove disallowed data **/
        if (!empty($this->allowedColumns))
        {
            foreach ($data as $key => $value)
            {
                if (!in_array($key, $this->allowedColumns))
                {
                    unset($data[$key]);
                }
            }
        }
        $keys = array_keys($data);
        $query = "update $this->table set ";
        foreach ($keys as $key)
        {
            $query .= $key . " = :" . $key . ", ";
        }

        $query = trim($query, ", ");
        $query .= " where $id_column = :$id_column";

        $data[$id_column] = $id;

        $this->query($query, $data);
        return false;
    }

    public function delete($id, $id_column = 'id')
    {
        $data[$id_column] = $id;
        $query = "delete from $this->table where $id_column = :$id_column";

        $data = array_merge($data);
        // echo $query;
        $this->query($query, $data);
        return false;
    }

    private function sanitizePaginationOrder(string $orderBy, string $orderDir, ?array $allowedOrderColumns): array
    {
        $fallback = 'id';

        if (!is_array($allowedOrderColumns) || empty($allowedOrderColumns))
        {
            $allowedOrderColumns = [$fallback];
        }

        $allowed = [];
        foreach ($allowedOrderColumns as $column)
        {
            $column = trim((string)$column);
            if ($this->isSafeIdentifier($column))
            {
                $allowed[] = $column;
            }
        }

        if (empty($allowed))
        {
            $allowed = [$fallback];
        }

        if (!in_array($orderBy, $allowed, true))
        {
            $orderBy = $allowed[0];
        }

        $orderDir = strtolower(trim($orderDir)) === 'asc' ? 'asc' : 'desc';

        return [$orderBy, $orderDir];
    }

    private function buildPaginateWhere(array $where): array
    {
        if (empty($where))
        {
            return ['', []];
        }

        $clauses = [];
        $params = [];
        $index = 0;

        foreach ($where as $column => $value)
        {
            $column = trim((string)$column);
            if (!$this->isSafeIdentifier($column))
            {
                continue;
            }

            $param = 'paginate_' . $index;
            $clauses[] = $column . ' = :' . $param;
            $params[$param] = $value;
            $index++;
        }

        if (empty($clauses))
        {
            return ['', []];
        }

        return [' where ' . implode(' && ', $clauses), $params];
    }

    private function isSafeIdentifier(string $value): bool
    {
        return (bool)preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value);
    }
}
