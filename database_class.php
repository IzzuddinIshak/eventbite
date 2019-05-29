<?php

   class User {
      var $user_id;
      var $username;
      var $password;
      var $email;
      var $contact;
   }

   class Event {
      var $event_id;
      var $name;
      var $time;
      var $description;
      var $price;
      var $addeddate;
      var $status;
   }

   class Record {
      var $event_id;
      var $user_id;
      var $username;
      var $name;
      var $time;
      var $price;
      var $addeddate;
   }

   class DbStatus {
      var $status;
      var $error;
      var $lastinsertid;
   }

   function time_elapsed_string($datetime, $full = false) {

      if ($datetime == '0000-00-00 00:00:00')
         return "none";

      if ($datetime == '0000-00-00')
         return "none";

      $now = new DateTime;
      $ago = new DateTime($datetime);
      $diff = $now->diff($ago);

      $diff->w = floor($diff->d / 7);
      $diff->d -= $diff->w * 7;

      $string = array(
         'y' => 'year',
         'm' => 'month',
         'w' => 'week',
         'd' => 'day',
         'h' => 'hour',
         'i' => 'minute',
         's' => 'second',
      );

      foreach ($string as $k => &$v) {
         if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
         } else {
            unset($string[$k]);
         }
      }

      if (!$full) $string = array_slice($string, 0, 1);
         return $string ? implode(', ', $string) . ' ago' : 'just now';
   }

	class Database {
 		  protected $dbhost;
    	protected $dbuser;
    	protected $dbpass;
    	protected $dbname;
    	protected $db;

 		function __construct( $dbhost, $dbuser, $dbpass, $dbname) {
   		$this->dbhost = $dbhost;
   		$this->dbuser = $dbuser;
   		$this->dbpass = $dbpass;
   		$this->dbname = $dbname;

   		$db = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
    		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
         $db->setAttribute(PDO::MYSQL_ATTR_FOUND_ROWS, true);
    		$this->db = $db;
   	}

      function beginTransaction() {
         try {
            $this->db->beginTransaction();
         }
         catch(PDOException $e) {
            $errorMessage = $e->getMessage();
            return 0;
         }
      }

      function commit() {
         try {
            $this->db->commit();
         }
         catch(PDOException $e) {
            $errorMessage = $e->getMessage();
            return 0;
         }
      }

      function rollback() {
         try {
            $this->db->rollback();
         }
         catch(PDOException $e) {
            $errorMessage = $e->getMessage();
            return 0;
         }
      }

      function close() {
         try {
            $this->db = null;
         }
         catch(PDOException $e) {
            $errorMessage = $e->getMessage();
            return 0;
         }
      }

      function insertUser($username, $clearpassword, $contact, $email) {

         //hash the password using one way md5 hashing
         $passwordhash = salt($clearpassword);
         try {

            $sql = "INSERT INTO user(username, password, contact, email, addeddate)
                    VALUES (:username, :password, :contact, :email, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam("username", $username);
            $stmt->bindParam("password", $passwordhash);
            $stmt->bindParam("contact", $contact);
            $stmt->bindParam("email", $email);
            $stmt->execute();

            $dbs = new DbStatus();
            $dbs->status = true;
            $dbs->error = "none";
            $dbs->lastinsertid = $this->db->lastInsertId();

            return $dbs;
         }
         catch(PDOException $e) {
            $errorMessage = $e->getMessage();

            $dbs = new DbStatus();
            $dbs->status = false;
            $dbs->error = $errorMessage;

            return $dbs;
         }
      }

      function authenticateUser($username) {
         $sql = "SELECT user_id, username, password as passwordhash
                 FROM user
                 WHERE username = :username";

         $stmt = $this->db->prepare($sql);
         $stmt->bindParam("username", $username);
         $stmt->execute();
         $row_count = $stmt->rowCount();

         $user = null;

         if ($row_count) {
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
               $user = new User();
               $user->user_id = $row['user_id'];
               $user->username = $row['username'];
               $user->passwordhash = $row['passwordhash'];
            }
         }

         return $user;
      }

      ///////////////////////////////////////////////////////////////////////////////////

      //get all events - home
      function getEvents() {
         $sql = "SELECT *
                 FROM event";

         $stmt = $this->db->prepare($sql);
         $stmt->execute();
         $row_count = $stmt->rowCount();

         $data = array();

         if ($row_count)
         {
            while($row = $stmt->fetch(PDO::FETCH_ASSOC))
            {
               $event = new Event();
               $event->event_id = $row['event_id'];
               $event->name = $row['name'];
               $event->time = $row['time'];
               $event->description = $row['description'];
               $event->price = $row['price'];

               $addeddate = $row['addeddate'];
               $event->addeddate = time_elapsed_string($addeddate);

               // $event->status = $row['status'];

               array_push($data, $event);
            }
         }

         return $data;
      }

      //view event
      function viewEvent($event_id) {
         $sql = "SELECT *
                 FROM event
                 WHERE event_id = :event_id";

         $stmt = $this->db->prepare($sql);
         $stmt->bindParam("event_id", $event_id);
         $stmt->execute();
         $row_count = $stmt->rowCount();

         $event = new Event();

         if ($row_count)
         {
            while($row = $stmt->fetch(PDO::FETCH_ASSOC))
            {
               $event->event_id = $row['event_id'];
               $event->name = $row['name'];
               $event->time = $row['time'];
               $event->price = $row['price'];
               $event->description = $row['description'];

               $addeddate = $row['addeddate'];
               $event->addeddate = time_elapsed_string($addeddate);
            }
         }

         return $event;
      }

      // pay event
      function buyEvent($event_id, $loginfo) {

         try {

            $sql = "INSERT INTO record(event_id, user_id, addeddate)
                    VALUES (:event_id, :loginfo, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam("event_id", $event_id);
            $stmt->bindParam("loginfo", $loginfo);
            $stmt->execute();

            $dbs = new DbStatus();
            $dbs->status = true;
            $dbs->error = "none";
            $dbs->lastinsertid = $this->db->lastInsertId();

            return $dbs;
         }
         catch(PDOException $e) {
            $errorMessage = $e->getMessage();

            $dbs = new DbStatus();
            $dbs->status = false;
            $dbs->error = $errorMessage;

            return $dbs;
         }
      }

      // insert event
      function insertEvent($name, $time, $price, $description) {

         try {

            $sql = "INSERT INTO event(name, time, price, description, addeddate)
                    VALUES (:name, :time, :price, :description, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam("name", $name);
            $stmt->bindParam("time", $time);
            $stmt->bindParam("price", $price);
            $stmt->bindParam("description", $description);
            // $stmt->bindParam("ownerlogin", $ownerlogin);
            $stmt->execute();

            $dbs = new DbStatus();
            $dbs->status = true;
            $dbs->error = "none";
            $dbs->lastinsertid = $this->db->lastInsertId();

            return $dbs;
         }
         catch(PDOException $e) {
            $errorMessage = $e->getMessage();

            $dbs = new DbStatus();
            $dbs->status = false;
            $dbs->error = $errorMessage;

            return $dbs;
         }
      }

      //get all events - manage
      function getManageEvents() {
         $sql = "SELECT *
                 FROM event";

         $stmt = $this->db->prepare($sql);
         $stmt->execute();
         $row_count = $stmt->rowCount();

         $data = array();

         if ($row_count)
         {
            while($row = $stmt->fetch(PDO::FETCH_ASSOC))
            {
               $event = new Event();
               $event->event_id = $row['event_id'];
               $event->name = $row['name'];
               $event->time = $row['time'];
               $event->description = $row['description'];
               $event->price = $row['price'];

               $addeddate = $row['addeddate'];
               $event->addeddate = time_elapsed_string($addeddate);

               // $event->status = $row['status'];

               array_push($data, $event);
            }
         }

         return $data;
      }

      //get single event
      function getEventViaId($event_id) {
         $sql = "SELECT *
                 FROM event
                 WHERE event_id = :event_id";

         $stmt = $this->db->prepare($sql);
         $stmt->bindParam("event_id", $event_id);
         $stmt->execute();
         $row_count = $stmt->rowCount();

         $event = new Event();

         if ($row_count)
         {
            while($row = $stmt->fetch(PDO::FETCH_ASSOC))
            {
               $event->event_id = $row['event_id'];
               $event->name = $row['name'];
               $event->time = $row['time'];
               $event->price = $row['price'];
               $event->description = $row['description'];

               $addeddate = $row['addeddate'];
               $event->addeddate = time_elapsed_string($addeddate);
            }
         }

         return $event;
      }

      // update event via id
      function updateEventViaId($event_id, $name, $time, $price, $description) {

         $sql = "UPDATE event
                 SET name = :name,
                     time = :time,
                     price = :price,
                     description = :description
                 WHERE event_id = :event_id";
         try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam("event_id", $event_id);
            $stmt->bindParam("name", $name);
            $stmt->bindParam("time", $time);
            $stmt->bindParam("price", $price);
            $stmt->bindParam("description", $description);
            $stmt->execute();

            $dbs = new DbStatus();
            $dbs->status = true;
            $dbs->error = "none";

            return $dbs;
         }
         catch(PDOException $e) {
            $errorMessage = $e->getMessage();

            $dbs = new DbStatus();
            $dbs->status = false;
            $dbs->error = $errorMessage;

            return $dbs;
         }
      }

      // delete contact via id
      function deleteEventViaId($event_id) {

         $dbstatus = new DbStatus();

         $sql = "DELETE
                 FROM event
                 WHERE event_id = :event_id";

         try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam("event_id", $event_id);
            $stmt->execute();

            $dbstatus->status = true;
            $dbstatus->error = "none";
            return $dbstatus;
         }
         catch(PDOException $e) {
            $errorMessage = $e->getMessage();

            $dbstatus->status = false;
            $dbstatus->error = $errorMessage;
            return $dbstatus;
         }
      }

      //get all records
      function getRecord() {
         $sql = "SELECT *
                  FROM record
                  JOIN event ON record.event_id = event.event_id
                  JOIN user ON record.user_id = user.user_id";

         $stmt = $this->db->prepare($sql);
         $stmt->execute();
         $row_count = $stmt->rowCount();

         $data = array();

         if ($row_count)
         {
            while($row = $stmt->fetch(PDO::FETCH_ASSOC))
            {
               $record = new Record();
               $record->event_id = $row['event_id'];
               $record->user_id = $row['user_id'];

               $record->username = $row['username'];
               $record->name = $row['name'];
               $record->time = $row['time'];
               $record->description = $row['description'];
               $record->price = $row['price'];

               $addeddate = $row['addeddate'];
               $record->addeddate = time_elapsed_string($addeddate);

               // $event->status = $row['status'];

               array_push($data, $record);
            }
         }

         return $data;
      }

      //get profile
      function getProfile($user_id) {
         $sql = "SELECT *
                 FROM user
                 WHERE user_id = :user_id";

         $stmt = $this->db->prepare($sql);
         $stmt->bindParam("user_id", $user_id);
         $stmt->execute();
         $row_count = $stmt->rowCount();

         $user = new User();

         if ($row_count)
         {
            while($row = $stmt->fetch(PDO::FETCH_ASSOC))
            {
               $user->user_id = $row['user_id'];
               $user->username = $row['username'];
               $user->email = $row['email'];
               $user->contact = $row['contact'];

               $addeddate = $row['addeddate'];
               $user->addeddate = time_elapsed_string($addeddate);
            }
         }

         return $user;
      }

      // update profile
      function updateProfile($user_id, $username, $email, $contact) {

         $sql = "UPDATE user
                 SET username = :username,
                     time = :time,
                     email = :email,
                     contact = :contact
                 WHERE $user_id = :$user_id";

         try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam("user_id", $user_id);
            $stmt->bindParam("username", $username);
            $stmt->bindParam("email", $email);
            $stmt->bindParam("contact", $contact);
            $stmt->execute();

            $dbs = new DbStatus();
            $dbs->status = true;
            $dbs->error = "none";

            return $dbs;
         }
         catch(PDOException $e) {
            $errorMessage = $e->getMessage();

            $dbs = new DbStatus();
            $dbs->status = false;
            $dbs->error = $errorMessage;

            return $dbs;
         }
      }


   }
