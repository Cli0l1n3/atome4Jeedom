<?php
	/* This file is part of Jeedom.
		*
		* Jeedom is free software: you can redistribute it and/or modify
		* it under the terms of the GNU General Public License as published by
		* the Free Software Foundation, either version 3 of the License, or
		* (at your option) any later version.
		*
		* Jeedom is distributed in the hope that it will be useful,
		* but WITHOUT ANY WARRANTY; without even the implied warranty of
		* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
		* GNU General Public License for more details.
		*
		* You should have received a copy of the GNU General Public License
		* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
	*/
	
	/* * ***************************Includes********************************* */
	require_once __DIR__ . "/../../../../core/php/core.inc.php";

	class atome extends eqLogic {

	// * Configuration *
		const URL_API = "https://esoftlink.esoftthings.com/api";
		const API_LOGIN = "/user/login.json";
		const URL_LOGIN = self::URL_API.self::API_LOGIN;
		
		const API_COMMON = "/subscription/";
		const API_CONSUMPTION = "/consumption.json?period=";
		const API_CURRENT_MEASURE = "/measure/live.json";
		const RESOURCES_DIR = __DIR__."/../../resources/";
		const COOKIES_FILE = self::RESOURCES_DIR."cookies.txt";
		const ATOME_FILE = self::RESOURCES_DIR."atome";
		
		const API_PERIOD = array(
								"sod" => array(
											array("id" => "consoKwhCurrentDay", "cmdName" => "Consommation", "unite" => "kWh", "type" => "info", "subType" => "numeric", "isHistorized" => 0, "eventOnly" => 1, "roundRule" => 2),
											array("id" => "consoEuroCurrentDay", "cmdName" => "Coût", "unite" => "Eur", "type" => "info", "subType" => "numeric", "isHistorized" => 0, "eventOnly" => 1, "roundRule" => 2)
										),
								"sow" => array(
											array("id" => "consoKwhCurrentWeek", "cmdName" => "Conso Semaine", "unite" => "kWh", "type" => "info", "subType" => "numeric", "isHistorized" => 0, "eventOnly" => 1, "roundRule" => 0),
											array("id" => "consoEuroCurrentWeek", "cmdName" => "Coût Semaine", "unite" => "Eur", "type" => "info", "subType" => "numeric", "isHistorized" => 0, "eventOnly" => 1, "roundRule" => 2)
										),
								"som" => array(
											array("id" => "consoKwhCurrentMonth", "cmdName" => "Conso Mois", "unite" => "kWh", "type" => "info", "subType" => "numeric", "isHistorized" => 0, "eventOnly" => 1, "roundRule" => 0),
											array("id" => "consoEuroCurrentMonth", "cmdName" => "Coût Mois", "unite" => "Eur", "type" => "info", "subType" => "numeric", "isHistorized" => 0, "eventOnly" => 1, "roundRule" => 0)
										), 
								"soy" => array(
											array("id" => "consoKwhCurrentYear", "cmdName" => "Conso Année", "unite" => "kWh", "type" => "info", "subType" => "numeric", "isHistorized" => 0, "eventOnly" => 1, "roundRule" => 0),
											array("id" => "consoEuroCurrentYear", "cmdName" => "Coût Année", "unite" => "Eur", "type" => "info", "subType" => "numeric", "isHistorized" => 0, "eventOnly" => 1, "roundRule" => 0)
										)
							);
		

		public function preUpdate() {

		}
		
		public function postUpdate() {
			log::add("atome", "debug", "Exécution de la fonction postUpdate");
			self::installCron();

			if ($this->getIsEnable()) {
				// Power command
				$cmd = $this->getCmd(null,"powerWCurrent");
				if (!is_object($cmd)) {
					$cmd = new atomeCmd();
					$cmd->setName("Puissance");
					$cmd->setEqLogic_id($this->getId());
					$cmd->setLogicalId("powerWCurrent");
					$cmd->setUnite("W");
					$cmd->setType("info");
					$cmd->setSubType("numeric");
					$cmd->setIsHistorized(0);
					$cmd->setEventOnly(1);
					$cmd->save();
				}
				
				// Consommation commands
				foreach (self::API_PERIOD as $periodValue => $arrayCommands) {
					foreach ($arrayCommands as $command) {
						log::add("atome", "info", "Create command: ".$command["id"]);
						$cmd = $this->getCmd(null, $command["id"]);
						if (!is_object($cmd)) {
							$cmd = new atomeCmd();
							$cmd->setName($command["cmdName"]);
							$cmd->setEqLogic_id($this->getId());
							$cmd->setLogicalId($command["id"]);
							$cmd->setUnite($command["unite"]);
							$cmd->setType($command["type"]);
							$cmd->setSubType($command["subType"]);
							$cmd->setIsHistorized($command["isHistorized"]);
							$cmd->setEventOnly($command["eventOnly"]);
							$cmd->save();
						}
					}
				}
			}
		}
		
		public function preRemove() {
			
		}
		
		public function postRemove() {
			
		}

		public static function getConsumptionEveryMinutes() {
			try {
				log::add("atome", "debug", "Consumption :: 0. Starting...");
				$eqLogics = eqLogic::byType("atome");
				foreach ($eqLogics as $eqLogic) {
					if ($eqLogic->getIsEnable() != 1) {
						log::add("atome", "error", "Consumption :: Aucun équipement n\'est configuré ou activé !");
						die();
					}
					
					log::add("atome", "debug", "Consumption :: 1. Get user details locally...");
					$userDetailForUrl = file_get_contents(self::ATOME_FILE);
					
					if ( empty($userDetailForUrl) ) {
						log::add("atome", "debug", "Consumption :: 1bis. No user authentication found. Try to login to Atome and retrieve User details");
						$userDetailForUrl = $eqLogic->loginAtomeAndGetLoginDetails($eqLogic->getConfiguration("identifiant"), $eqLogic->getConfiguration("password"));
						
						log::add("atome", "debug", "Consumption :: 1ter. Save user details [".$userDetailForUrl."]...");
						file_put_contents(self::ATOME_FILE, $userDetailForUrl);
					}
					
					// Configuration de l'url pour récupérer la consommation du jour en cours
					foreach (self::API_PERIOD as $periodValue => $arrayCommands) {
						log::add("atome", "debug", "Consumption :: 2. Generate url to call for period ".$periodValue);
						$urlApi = self::URL_API.self::API_COMMON.$userDetailForUrl.self::API_CONSUMPTION.$periodValue;
					
						// Call Atome API
						log::add("atome", "debug", "Consumption :: 3. call Atome API");
						$jsonDataResponse = $eqLogic->callAtomeCommandAPI($urlApi, $eqLogic->getConfiguration("identifiant"), $eqLogic->getConfiguration("password"), 0);
						
						//Save data into Database
						log::add("atome", "debug", "Consumption :: 4. read response and save data");
						$eqLogic->saveAtomeConsumptionEvent($jsonDataResponse, $arrayCommands);
					}
				}
				log::add("atome", "debug", "Consumption :: 5. Finished...");
			} catch (Exception $e) {
                log::add("atome", "error", "Consumption :: Exception : ".$e->getMessage());
                die();
            }
		}
		
		public static function getCurrentPowerEveryTwentySeconds() {
			try {
				log::add("atome", "debug", "Power :: 0. Starting...");
				$eqLogics = eqLogic::byType("atome");
				foreach ($eqLogics as $eqLogic) {
					if ($eqLogic->getIsEnable() != 1) {
						log::add("atome", "error", "Power :: Aucun équipement n\'est configuré ou activé !");
						die();
					}
					
					log::add("atome", "debug", "Power :: 1. Get user details saved locally...");
					$userDetailForUrl = file_get_contents(self::ATOME_FILE);
					
					if ( empty($userDetailForUrl) ) {
						log::add("atome", "debug", "Power :: 1bis. No user authentication found. Try to login to Atome and retrieve User details");
						$userDetailForUrl = $eqLogic->loginAtomeAndGetLoginDetails($eqLogic->getConfiguration("identifiant"), $eqLogic->getConfiguration("password"));
						
						log::add("atome", "debug", "Power :: 1ter. Save user details [".$userDetailForUrl."]...");
						file_put_contents(self::ATOME_FILE, $userDetailForUrl);
					}
					
					// Configuration de l'url pour récupérer la consommation du jour en cours
					log::add("atome", "debug", "Power :: 2. Generate url to call");
					$urlApi = self::URL_API.self::API_COMMON.$userDetailForUrl.self::API_CURRENT_MEASURE;
					
					// Call Atome API
					log::add("atome", "debug", "Power :: 3. call Atome API: ".$urlApi);
					$jsonDataResponse = $eqLogic->callAtomeCommandAPI($urlApi, $eqLogic->getConfiguration("identifiant"), $eqLogic->getConfiguration("password"), 0);
					
					//Save data into Database
					log::add("atome", "debug", "Power :: 4. read response and save data");
					$eqLogic->saveAtomeCurrentPowerEvent($jsonDataResponse);
					
					
				}
				log::add("atome", "debug", "Power :: 5. Finished...");
			} catch (Exception $e) {
                log::add("atome", "error", "Power :: Exception : ".$e->getMessage());
                die();
            }
		}
		

		/* INSTALLATION DES CRONS EXPRESSIONS */
		public function installCron() {
			log::add("atome", "debug", "Install :: Vérification des crons");
			$this->checkCronAndCreateIfNecessary("getConsumptionEveryMinutes", "* * * * *");
			$this->checkCronAndCreateIfNecessary("getCurrentPowerEveryTwentySeconds", "* * * * *");
		}
		
		/*****************************
		 * PRIVATE METHODS
		 *****************************/

		private function saveAtomeCurrentPowerEvent($jsonDataResponse) {
            try {
				// Get datas
				$powerTime = $jsonDataResponse->time;
				$powerLast = $jsonDataResponse->last;
				$powerFilteredPower = $jsonDataResponse->filteredPower;
				log::add("atome", "info", "Power :: powerTime=[".$powerTime."], powerLast=[".$powerLast."], powerFilteredPower=[".$powerFilteredPower."]");
				
                $startDate = new DateTime($consoTime);
				$this->getCmd(null, "powerWCurrent")->event($powerFilteredPower, $startDate->format($dateFormat));
            } catch (Exception $e) {
                log::add("atome", "error", "Exception : ".$e->getMessage());
                die();
            }
        }
		
		private function saveAtomeConsumptionEvent($jsonDataResponse, $arrayCommands) {
            try {
				// Get datas
				$consoTime = $jsonDataResponse->time;
				$consoTotal = $jsonDataResponse->total / 1000;
				$consoPrice = $jsonDataResponse->price;
				$consoStart = $jsonDataResponse->startPeriod;
				$consoEnd = $jsonDataResponse->endPeriod;
				$consoImpactCO2 = $jsonDataResponse->impactCo2;
				log::add("atome", "info", "Consumption :: consoTime=[".$consoTime."], consoTotal=[".$consoTotal."], consoPrice=[".$consoPrice."], consoStart=[".$consoStart."], consoEnd=[".$consoEnd."], consoImpactCO2=[".$consoImpactCO2."]");
				
                $startDate = new DateTime($consoTime);
				foreach ($arrayCommands as $command) {
					if ($command["unite"] === "Eur") {
						$this->getCmd(null, $command["id"])->event(round($consoPrice, $command["roundRule"], PHP_ROUND_HALF_UP), $startDate->format($dateFormat));
					} else if ($command["unite"] === "kWh") {
						$this->getCmd(null, $command["id"])->event(round($consoTotal, $command["roundRule"], PHP_ROUND_HALF_UP), $startDate->format($dateFormat));
					} else {
						;
					}
				}
            } catch (Exception $e) {
                log::add("atome", "error", "Exception : ".$e->getMessage());
                die();
            }
        }
		
		private function checkCronAndCreateIfNecessary($cronName, $cronSchedule) {
			$cron = cron::byClassAndFunction("atome", $cronName);
			if (is_object($cron)) {
				log::add("atome", "debug", "Install :: Cron ".$cronName." existe déjà. On La stop et on la delete");
				$cron->stop();
				$cron->remove();
			}
			log::add("atome", "debug", "Install :: Cron ".$cronName." inexistant, il faut le créer");
			$cron = new cron();
			$cron->setClass("atome");
			$cron->setFunction($cronName);
			$cron->setEnable(1);
			$cron->setDeamon(0);
			$cron->setSchedule($cronSchedule);
			$cron->save();
		}
				
		private function loginAtomeAndGetLoginDetails($login, $password) {
			if (!empty($login) && !empty($password) ) {
				// Login to Atome
				log::add("atome", "info", "Connexion à Atome...");
				$jsonLoginResponse = $this->callAtomeLogin($login, $password);
				
				// Extraction des infos utilisateurs
				log::add("atome", "debug", "Extract and return user details (Id/Subscription)...");				
				return $this->extractUserDetails($jsonLoginResponse);
			}
		}

		private function callAtomeLogin($login, $password) {
			// 1. Test de l'écriture sur les fichiers du dossier resources.
			$this->checkWriteRights($response);
			
			// 2. Authentification sur Atome
			log::add("atome", "info", "Login :: Execute cURL command for Authentication");
			$response = $this->execCurlLoginCommand($login, $password);

			// 3. Decode json response
			log::add("atome", "debug", "Login :: Lecture de la réponse json");
			$jsonResponse = json_decode($response);

			if ($jsonResponse->errors) {
				log::add("atome", "debug", "Login :: Erreur à la connexion API: ".$jsonResponse->errors);
				die();
			}
			
			log::add("atome", "debug", "Connexion réussie...");
			return $jsonResponse;
		}
		
		private function extractUserDetails($jsonResponse) {
			if ( empty($jsonResponse->subscriptions) ) {
				log::add("atome", "error", "No information found from user");
				die();
			}
			return $jsonResponse->id."/".$jsonResponse->subscriptions[0]->reference;
		}

		
		private function callAtomeCommandAPI($urlApi, $login, $password, $securityCounter) {	
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_COOKIEFILE => self::COOKIES_FILE,
				CURLOPT_COOKIEJAR => self::COOKIES_FILE,
				CURLOPT_COOKIESESSION => true,
				CURLOPT_CUSTOMREQUEST => "GET",
				CURLOPT_ENCODING => "",
				CURLOPT_HTTPHEADER => array(
					"Cache-Control: no-cache",
					"Content-Type: application/json",
				),
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_URL => $urlApi
			));

			$response = curl_exec($curl);
			$curlError = curl_error($curl);
			curl_close($curl);

			if ($curlError) {
				log::add("atome", "error", "cURL Error while calling Command API: ".$curlError);
				die();
			}
			
			$decodeResponse = json_decode($response);
			
			if ($decodeResponse->message && $securityCounter < 1) {
				log::add("atome", "debug", "Got disconnected from Atome API. Call Login Method again [message: ".$decodeResponse->message."]");
				$this->callAtomeLogin($login, $password);
				return $this->callAtomeCommandAPI($urlApi, $login, $password, $securityCounter++);
			}
			return $decodeResponse;
		}
		
		private function execCurlLoginCommand($login, $password) {
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_COOKIEFILE => self::COOKIES_FILE,
				CURLOPT_COOKIEJAR => self::COOKIES_FILE,
				CURLOPT_COOKIESESSION => true,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_ENCODING => "",
				CURLOPT_HTTPHEADER => array(
					"Cache-Control: no-cache",
					"Content-Type: application/json"
				),
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_POSTFIELDS => "{\"email\": \"".$login."\",\"plainPassword\": \"".$password."\"}",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_URL => self::URL_LOGIN
			));
	
			$response = curl_exec($curl);
			$curlError = curl_error($curl);
			curl_close($curl);

			if ($curlError) {
				log::add("atome", "error", "cURL Error while calling login API: ".$curlError);
				die();
			}
			return $response;
		}
			
		private function checkWriteRights($response) {
			if (false === is_writable(self::RESOURCES_DIR)) {
                log::add("atome", "error", "Le dossier ".self::RESOURCES_DIR." n\"est pas accessible en écriture !");
                die();
            }
		}
	}

	class atomeCmd extends cmd {
		public function execute($_options = array()) {
			
		}
	}
?>