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
	
	require_once dirname(__FILE__)."/../../../core/php/core.inc.php";
	
	function installAtomeJeedom() {
		log::add("atome", "debug", "Installation du plugin Atome");
		atome::installCron();
	}
	
	function updateAtomeJeedom() {
		log::add("atome", "debug", "Mise à jour du plugin Atome");
		atome::installCron();
	}
	
	function removeAtomeJeedom() {
		$cron = cron::byClassAndFunction("atome", "getCurrentDailyConsumptionEveryMinutes");
		if (is_object($cron)) {
			log::add("atome", "debug", "Arrêt du cron getCurrentDailyConsumptionEveryMinutes");
			$cron->stop();
			log::add("atome", "debug", "Suppression du cron getCurrentDailyConsumptionEveryMinutes");
			$cron->remove();
		}

		$cron = cron::byClassAndFunction("atome", "getCurrentPowerEveryTwentySeconds");
		if (is_object($cron)) {
			log::add("atome", "debug", "Arrêt du cron getCurrentPowerEveryTwentySeconds");
			$cron->stop();
			log::add("atome", "debug", "Suppression du cron getCurrentPowerEveryTwentySeconds");
			$cron->remove();
		}
	}
?>