<?php
    /**
     * Coder: Nguyễn Gia Huy
     * PHP Version >= 7.0.33
     * Bắt đầu: 13-03-2019
     * Sửa đổi cuối: 14-04-2019
     * 
     * 
     */

    /**
     * Kết nối đến CSDL mysql
     */
    class DB {

        /**
         * Kêt nối CSDL
         *
         * @return DB_Connection
         */
        public static function connection(
            $server = HOSTDB,
            $user   = USERDB,
            $pass   = PASSDB,
            $db     = NAMEDB
            ) : DB_Connection {

            try {
                $pdo = new PDO(DB::string_connect($server, $db), $user, $pass, DB::options());
                return new DB_Connection($pdo);
            }
            catch(PDOException $e ){
                echo $e->getMessage();
                exit();
            }
        }

        private static function options(){
            return array(
                PDO::MYSQL_ATTR_INIT_COMMAND    =>  "SET NAMES UTF8",
                PDO::ATTR_ERRMODE               =>  PDO::ERRMODE_EXCEPTION
            );
        }

        private static function string_connect(string $host, string $db){
            return "mysql:host=$host; dbname=$db";
        }
    }


    /**
     * Hỗ trợ các query trong CSDL
     * Có thể khởi tạo gián tiếp qua:
     * 
     *      DB::connection()
     * 
     */
    class DB_Connection {

        /**
         * PDO Mysql
         *
         * @var PDO
         */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        public function get_pdo() : PDO {
            return $this->pdo;
        }

        public function query(string $query, array $params = array()) : DB_Query {

            return new DB_Query($this->pdo, $query, $params);
        }

        public function table(string $table) : DB_QueryBuilder {
            $qr = new DB_QueryBuilder($this->pdo);
            $qr->table($table);
            return $qr;
        }

    }



    /**
     * Hỗ trợ thực thi qua query
     * Được tao nhanh gián tiếp qua:
     * 
     *      DB::connection()->query()
     *
     */
    class DB_Query {

        private $pdo;
        private $query;
        private $params;

        /**
         * Khởi tạo query
         *
         * @param PDO $pdo
         * @param string $query
         * @param array $params
         */
        public function __construct(PDO $pdo, string $query, array $params = array())
        {
            $this->pdo = $pdo;
            $this->query = $query ?? "";
            $this->params = $params;            
        }



        /**
         * Tiến hành query và nhận với các mãng giá trị
         *
         * @return array
         */
        public function executeReader() : array {
            $this->onExecute();
            $stmts = $this->pdo->prepare($this->query);
            $stmts->execute($this->params);
            return $stmts->fetchAll();
        }



        /**
         * Tiến hành query và nhận về số dòng bị ảnh hưởng
         *
         * @return integer
         */
        public function executeNonQuery() : int {
            $this->onExecute();
            $stmts = $this->pdo->prepare($this->query);
            $stmts->execute($this->params);
            return $stmts->rowCount();
        }



        /**
         * Tiến hành query và nhận về số dòng được truy vần
         *
         * @return integer
         */
        public function exectuteScalar() : int {
            $this->onExecute();
            $query = preg_replace('/\sSELECT\s*(.*)\s*FROM\s/', ' SELECT COUNT(*) _y_count__ FROM ', $this->query);
            $stmts = $this->pdo->prepare($query);
            $stmts->execute($this->params);
            return $stmts->fetchAll()[0]['_y_count__'];
        }


        /**
         * Đắng kí lằng nghe khi có hành động execute
         *
         * @return void
         */
        protected function onExecute() {
        }



        public function setPDO(PDO $pdo){
            $this->pdo = $pdo;
        }

        public function setQuery(string $query) : DB_Query {
            $this->query = $query;
            return $this;
        }

        public function setParams(array $params) : DB_Query {
            $this->params = $params;
            return $this;
        }

        public function getQuery(){
            return $this->query;
        }

        public function getParams(){
            return $this->params;
        }

    }


    define('ORDER_BY_DESC', 'DESC');
    define('ORDER_BY_ASC',  'ASC');

    /**
     * Xây dựng các query dễ nhìn hơn
     * Có tấc cả các đặc điểm của DB_Query
     * Xây dựng nhanh qua
     * 
     *      DB::connection()->table()
     * 
     */
    class DB_QueryBuilder extends DB_Query {

        private $select;
        private $from;
        private $where_;
        private $groupby_;
        private $having_;
        private $orderby_;
        private $limit_start;
        private $limit_num;

        private $data_query = array();

        private $type_query = "select";




        public function __construct(PDO $pdo)
        {
            parent::__construct($pdo, "", array());
            $this->select = null;
            $this->from = null;
            $this->where_ = null;
            $this->groupby_ = null;
            $this->having_ = null;
            $this->orderby_ = null;
            $this->limit_start = -1;
            $this->limit_num = -1;
        }

        protected function onExecute() {
            switch($this->type_query){
                case 'select':
                    $select = " SELECT ".($this->select ?? "*");
                    $from   = " FROM ".$this->from;
                    $where  = isset($this->where_) ? " WHERE $this->where_" : "";
                    $orderby = isset($this->orderby_) ? " ORDER BY $this->orderby_" : "";
                    $limit = $this->limit_start != -1 ? " LIMIT $this->limit_start , $this->limit_num " : "";
                    parent::setQuery($select.$from.$where.$orderby.$limit);
                    break;

                case 'insert':
                    $str_column = "";
                    $str_values = "";
                    $params = array();

                    foreach ($this->data_query as $key => $value){
                        $str_column.= "$key,";
                        $str_values.= "?,";
                        array_push($params, $value);
                    }

                    $str_column = substr($str_column, 0, strlen($str_column) - 1);
                    $str_values = substr($str_values, 0, strlen($str_values) - 1);

                    parent::setParams($params);
                    parent::setQuery("INSERT INTO $this->from($str_column) VALUES ($str_values)");
                    break;
            }
        }

        public function table(string $table) : DB_QueryBuilder {
            $this->from = $table;
            return $this;
        }


        public function select(string $select) : DB_QueryBuilder {
            $this->select = $select;
            return $this;
        }

        public function where(string $where) : DB_QueryBuilder {
            $this->where_ = $where;
            return $this;
        }

        public function orderby(string $ordby, string $sort = DB_QueryBuilder::DESC) : DB_QueryBuilder {
            $this->orderby_ =$ordby;
            return $this; 
        }

        public function limit(int $start, int $num) : DB_QueryBuilder {
            $this->limit_num = $num;
            $this->limit_start = $start;
            return $this;
        }

        public function insert(array $data) : bool {
            $this->type_query = 'insert';
            $this->data_query = $data;
            $status = parent::executeNonQuery() > 0;
            $this->type_query = 'select';
            $this->data_query = array();
            return $status;
        }
    }

?>