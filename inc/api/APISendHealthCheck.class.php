<?php

use DBA\Factory;
use DBA\HealthCheckAgent;
use DBA\QueryFilter;

class APISendHealthCheck extends APIBasic {
  public function execute($QUERY = array()) {
    if (!PQuerySendHealthCheck::isValid($QUERY)) {
      $this->sendErrorResponse(PActions::SEND_HEALTH_CHECK, "Invalid send health check query!");
    }
    $this->checkToken(PActions::SEND_HEALTH_CHECK, $QUERY);
    $this->updateAgent(PActions::SEND_HEALTH_CHECK);
    
    $healthCheck = Factory::getHealthCheckFactory()->get($QUERY[PQuerySendHealthCheck::CHECK_ID]);
    if ($healthCheck == null) {
      // for whatever reason there is no check available anymore
      $this->sendErrorResponse(PActions::SEND_HEALTH_CHECK, "Invalid health check id!");
    }
    $qF1 = new QueryFilter(HealthCheckAgent::HEALTH_CHECK_ID, $healthCheck->getId(), "=");
    $qF2 = new QueryFilter(HealthCheckAgent::AGENT_ID, $this->agent->getId(), "=");
    $healthCheckAgent = Factory::getHealthCheckAgentFactory()->filter([Factory::FILTER => [$qF1, $qF2]], true);
    if ($healthCheckAgent == null) {
      // for whatever reason there is no check available anymore
      $this->sendErrorResponse(PActions::SEND_HEALTH_CHECK, "Invalid health check agent id!");
    }
    
    $numCracked = intval($QUERY[PQuerySendHealthCheck::NUM_CRACKED]);
    $numGpus = intval($QUERY[PQuerySendHealthCheck::NUM_GPUS]);
    $errors = $QUERY[PQuerySendHealthCheck::ERRORS];
    $start = intval($QUERY[PQuerySendHealthCheck::START]);
    $end = intval($QUERY[PQuerySendHealthCheck::END]);
    
    if (!is_array($errors)) {
      $errors = [$errors];
    }
    
    $status = DHealthCheckAgentStatus::COMPLETED;
    if (sizeof($errors) > 0 && $this->agent->getIgnoreErrors() == DAgentIgnoreErrors::NO) {
      $status = DHealthCheckAgentStatus::FAILED;
    }
    else if ($numCracked != $healthCheck->getExpectedCracks()) {
      $status = DHealthCheckAgentStatus::FAILED;
    }
    
    $healthCheckAgent->setCracked($numCracked);
    $healthCheckAgent->setNumGpus($numGpus);
    $healthCheckAgent->setErrors(json_encode($errors));
    $healthCheckAgent->setStart($start);
    $healthCheckAgent->setEnd($end);
    $healthCheckAgent->setStatus($status);
    Factory::getHealthCheckAgentFactory()->update($healthCheckAgent);
    
    DServerLog::log(DServerLog::DEBUG, "Agent sent health check results", [$this->agent, $healthCheck, $healthCheckAgent]);
    
    HealthUtils::checkCompletion($healthCheck);
    $this->sendResponse([
        PResponseSendHealthCheck::ACTION => PActions::SEND_HEALTH_CHECK,
        PResponseSendHealthCheck::RESPONSE => PValues::OK
      ]
    );
  }
}