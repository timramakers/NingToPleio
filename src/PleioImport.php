<?php
	class PleioImport {
		private $savePath;
		private	$importPath;
		
		function __construct($savePath,$importPath) {
			$this->savePath = $savePath;
			$this->importPath = $importPath;
		}
		
		function readJSON($path){
			// get json file contents
			$json = file_get_contents($path);
			// replace for ning shortcomings
			$json = preg_replace( "|^\(|", "", $json );
			$json = preg_replace( "|\)$|", "", $json );
			$json = str_replace( "}{", "},{", $json );
			$json = str_replace( "]{", ",{", $json );
			$json = str_replace( "] {", ",{", $json );
			// make sure it's UTF-8
			$json = utf8_encode($json);
			// go ahead and decode
			$data = json_decode($json,true);
			// php inferior default JSON error handling
			if($data == null){
				throw new Exception("JSON Validation error reading " . $path . ": ".json_last_error_msg());
			}
			return $data;
		}
		
		function writeToFile($filename,$data){
			// check data
			if($data == null){
				throw new Exception("No data to save");
			}
			
			// create json
			$json = "";
			foreach($data as $object){
				// convert to json, add EOL after each object
				$json .= json_encode($object,JSON_FORCE_OBJECT) . PHP_EOL;
			}
			
			file_put_contents($this->savePath . "/" . $filename . ".json", $json);
			unset($json);
			unset($data);
			return true;
		}
		
		function copyFile($from,$to){
			$from = $this->importPath . "/" . $from;
			$to = $this->savePath . "/files/" . $to;
			// copy to files folder in target path while keeping subfolder per type
			$path = pathinfo($to);
			if (!file_exists($path["dirname"])) {
		        mkdir($path["dirname"], 0777, true);
		    } 
			copy($from,$to);
			return true;
		}
		
		function convertMembers($path){
			$mappings =   [	"fullName" => "name" ,
							"email" => "email" ,
							"location" => "location",
							"country" => "country",
							"birthdate" => "birthdate",
							"profilePhoto" => "profile_photo",
							"state" => "blocked" ]; // suspended = yes
			$pleioUsers = [];
			$ningMembers = $this->readJSON($path);
			foreach($ningMembers as $ningMember){
				$pleioUser = [];
				foreach($mappings as $ning => $pleio){
					if($ning == "state"){
						// state suspended = blocked yes
						if($ningMember[$ning] == "suspended"){
							$ningMember[$ning] = "yes";
						} else {
							$ningMember[$ning] = "no";
						}
					}
					if($ning == "profilePhoto"){
						// move file
						$filename = strtok($ningMember[$ning], '?');
						$this->copyFile($filename,$filename);
					}
					$pleioUser[$pleio] = $ningMember[$ning];
				}
				$pleioUsers[] = $pleioUser;
			}
			unset($json);
			unset($ningMembers);
			$this->writeToFile('users',$pleioUsers);
			return true;
		}
		
		function convertEvents($path){
			$json = $this->readJSON($path);
			$mappings =   [	"title" => "title" ,
							"description" => "description" ,
							"startDate" => "start_date",
							"endDate" => "end_date",
							"location" => "location",
							"attendees" => "rsvp"];
			$pleioEvents = [];
			$ningEvents = $this->readJSON($path);
			foreach($ningEvents as $ningEvent){
				$pleioEvent = ["type" => "object", "subtype" => "event"];
				foreach($mappings as $ning => $pleio){
					if($ning == "attending"){
						// transform rsvps
						$attending = [];
						foreach($ningEvent[$ning] as $key => $rsvp){
							$attending[] = ["user" => $rsvp["contributorName"], "rsvp" => $rsvp["attendeeStatus"]];
						}
						if(count($attending)){
							$ningEvent[$ning] = $attending;
						} else {
							unset($ningEvent[$ning]);
						}
					}
					$pleioEvent[$pleio] = $ningEvent[$ning];
				}
				$pleioEvents[] = $pleioEvent;
			}
			unset($json);
			unset($ningEvents);
			$this->writeToFile('events',$pleioEvents);
			return true;
		}
		
		function convertGroups($path){
			$json = $this->readJSON($path);
			$mappings =   [	"title" => "title" ,
							"description" => "description" ,
							"contributorName" => "owner",
							"groupPrivacy" => "privacy",
							"members" => "members"];
			$pleioGroups = [];
			$ningGroups = $this->readJSON($path);
			foreach($ningGroups as $ningGroup){
				$pleioGroup = ["type" => "object", "subtype" => "group"];
				foreach($mappings as $ning => $pleio){
					if($ning == "members"){
						// transform members
						$members = [];
						foreach($ningGroup[$ning] as $key => $member){
							$members[] = ["user" => $member["contributorName"], "name" => $member["fullName"], "status" => $member["status"]];
						}
						if(count($members)){
							$ningGroup[$ning] = $members;
						} else {
							unset($ningGroup[$ning]);
						}
					}
					$pleioGroup[$pleio] = $ningGroup[$ning];
				}
				$pleioGroups[] = $pleioGroup;
			}
			unset($json);
			unset($ningGroups);
			$this->writeToFile('groups',$pleioGroups);
			return true;
		}
		
		function convertDiscussions($path){
			$json = $this->readJSON($path);
			$mappings =   [	"title" => "title" ,
							"description" => "description" ,
							"createdDate" => "created_date",
							"comments" => "comments",
							"fileAttachments" => "attachments"];
			$pleioDiscussions = [];
			$ningDiscussions = $this->readJSON($path);
			foreach($ningDiscussions as $ningDiscussion){
				$pleioDiscussie = ["type" => "object", "subtype" => "discussion"];
				foreach($mappings as $ning => $pleio){
					if($ning == "comments"){
						// transform comments
						$comments = [];
						foreach($ningDiscussion[$ning] as $key => $comment){
							$comments[] = ["user" => $comment["contributorName"], 
											"description" => $comment["description"], 
											"created_date" => $comment["createdDate"]];
						}
						if(count($comments)){
							$ningDiscussion[$ning] = $comments;
						} else {
							unset($ningDiscussion[$ning]);
						}
					}
					if($ning == "fileAttachments"){
						// transform attachments
						$attachments = [];
						foreach($ningDiscussion[$ning] as $key => $filename){
							$filename = strtok($filename, '?');
							// move file
							$this->copyFile($filename,$filename);
							// add attachment to json for future proper use
							$attachments[] = "/files/" . $filename;
							// add attachment to description due to current Pleio limitations
							$pleioDiscussie["description"] .= "<br /><a href=\"/files/" . $filename . ">" . basename($filename) . "</a>";
						}
						if(count($attachments)){
							$ningDiscussion[$ning] = $attachments;
						} else {
							unset($ningDiscussion[$ning]);
						}
					}
					$pleioDiscussie[$pleio] = $ningDiscussion[$ning];
				}
				$pleioDiscussies[] = $pleioDiscussie;
			}
			unset($json);
			unset($ningDiscussions);
			$this->writeToFile('discussions',$pleioDiscussies);
			return true;
		}
		
		function convertBlogs($path){
			$json = $this->readJSON($path);
			$mappings =   [	"title" => "title" ,
							"description" => "description" ,
							"createdDate" => "created_date",
							"comments" => "comments"];
			$pleioBlogs = [];
			$ningBlogs = $this->readJSON($path);
			foreach($ningBlogs as $ningBlog){
				$pleioBlog = ["type" => "object", "subtype" => "Blog"];
				foreach($mappings as $ning => $pleio){
					if($ning == "comments"){
						// transform comments
						$comments = [];
						foreach($ningBlog[$ning] as $key => $comment){
							$comments[] = ["user" => $comment["contributorName"], 
											"description" => $comment["description"], 
											"created_date" => $comment["createdDate"]];
						}
						if(count($comments)){
							$ningBlog[$ning] = $comments;
						} else {
							unset($ningBlog[$ning]);
						}
					}
					if($ning == "fileAttachments"){
						// transform attachments
						$attachments = [];
						foreach($ningBlog[$ning] as $key => $filename){
							$filename = strtok($filename, '?');
							// move file
							$this->copyFile($filename,$filename);
							// add attachment to json for future proper use
							$attachments[] = "/files/" . $filename;
							// add attachment to description due to current Pleio limitations
							$pleioDiscussie["description"] .= "<br /><a href=\"/files/" . $filename . ">" . basename($filename) . "</a>";
						}
						if(count($attachments)){
							$ningBlog[$ning] = $attachments;
						} else {
							unset($ningBlog[$ning]);
						}
					}
					$pleioBlog[$pleio] = $ningBlog[$ning];
				}
				$pleioBlogs[] = $pleioBlog;
			}
			unset($json);
			unset($ningBlogs);
			$this->writeToFile('blogs',$pleioBlogs);
			return true;
		}
		
		function convert(){
			// members
			$this->convertMembers($this->importPath . "/ning-members-local.json");
			
			// events
			$this->convertEvents($this->importPath . "/ning-events-local.json");
			
			// groups
			$this->convertGroups($this->importPath . "/ning-groups-local.json");
			
			// discussions
			$this->convertDiscussions($this->importPath . "/ning-discussions-local.json");
			
			// blogs
			$this->convertBlogs($this->importPath . "/ning-blogs-local.json");
			
			echo "Conversion successful!";
		}
	}
?>