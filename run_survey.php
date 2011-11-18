<?php
// Copyright (C) 2010 Combodo SARL
//
//   This program is free software; you can redistribute it and/or modify
//   it under the terms of the GNU General Public License as published by
//   the Free Software Foundation; version 3 of the License.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of the GNU General Public License
//   along with this program; if not, write to the Free Software
//   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

/**
 * iTop User Portal main page
 *
 * @author      Erwan Taloc <erwan.taloc@combodo.com>
 * @author      Romain Quetiez <romain.quetiez@combodo.com>
 * @author      Denis Flaven <denis.flaven@combodo.com>
 * @license     http://www.opensource.org/licenses/gpl-3.0.html LGPL
 */
require_once('../../approot.inc.php');
require_once(APPROOT.'/application/application.inc.php');
require_once(APPROOT.'/application/nicewebpage.class.inc.php');
require_once(APPROOT.'/application/wizardhelper.class.inc.php');


function ReadMandatoryParam($sParam, $sSanitizationFilter = 'parameter')
{
	$value = utils::ReadParam($sParam, null, false /*allow CLI*/, $sSanitizationFilter);
	if (is_null($value))
	{
		throw new Exception("Missing argument '$sParam'");
	}
	return $value; 
}


function GetContext($sToken)
{
	// Find the corresponding survey target -> survey -> quiz
	//
	$oTargetSearch = DBObjectSearch::FromOQL("SELECT SurveyTargetAnswer WHERE token = :token");
	$oTargetSet = new CMDBObjectSet($oTargetSearch, array(), array('token' => $sToken));
	if ($oTargetSet->Count() == 0)
	{
		throw new Exception("Unkown token '$sToken'");
	}
	$oTarget = $oTargetSet->Fetch();
	$oSurvey = MetaModel::GetObject('Survey', $oTarget->Get('survey_id'));
	$oQuiz = MetaModel::GetObject('Quiz', $oSurvey->Get('quiz_id'));

  	// Find the questions
	//
	$oQuestionSearch = DBObjectSearch::FromOQL("SELECT QuizQuestion WHERE quiz_id = :quiz");
	$oQuestionSet = new CMDBObjectSet($oQuestionSearch, array('order' => true), array('quiz' => $oQuiz->GetKey()));
	if ($oQuestionSet->Count() == 0)
	{
		throw new Exception("Sorry, there is no question for this quiz (?!)");
	}

	return array($oTarget, $oSurvey, $oQuiz, $oQuestionSet);
}


function ShowDraftQuiz($oP, $iQuiz)
{
	$oQuiz = MetaModel::GetObject('Quiz', $iQuiz, false);
	if ($oQuiz)
	{
		$oP->set_title(Dict::S('Survey-Title-Draft'));
		$oQuiz->ShowForm($oP);
	}
	else
	{
		$oP->p("Invalid value for quiz_id: '$iQuiz'");
	}
}

function ShowQuiz($oP, $sToken)
{
	list($oTarget, $oSurvey, $oQuiz, $oQuestionSet) = GetContext($sToken);


	if (strlen($oTarget->Get('date_response')) > 0)
	{
		$oP->p(Dict::Format('***You have already answered (%1$s)', $oTarget->Get('date_response')));
	}
	elseif($oSurvey->Get('status') != 'running')
	{
		$oP->p(Dict::S('***Sorry, the survey has been closed'));
	}
	else
	{
		$oQuiz->ShowForm($oP, $oSurvey, $oTarget);
	}
}

function SubmitAnswers($oP, $sToken)
{
	list($oTarget, $oSurvey, $oQuiz, $oQuestionSet) = GetContext($sToken);

	$aAnsers = ReadMandatoryParam('answer', 'raw_data');
	$sComment = ReadMandatoryParam('comment', 'raw_data');

	// Todo - check if there are already some answers (to update)

	$oMyChange = MetaModel::NewObject("CMDBChange");
	$oMyChange->Set("date", time());
	$sUserString = CMDBChange::GetCurrentUserName();
	$oMyChange->Set("userinfo", $sUserString);
	$iChangeId = $oMyChange->DBInsert();

	// Foreach question, find the answer
	//
	while($oQuestion = $oQuestionSet->Fetch())
	{
		$iQuestion = $oQuestion->GetKey();
		
		if (!isset($aAnsers[$iQuestion]))
		{
// TODO: understand why ???
//			$oP->add("<p>Missing answer for question #$iQuestion</p>\n");
		}
		else
		{
			$oAnswer = new SurveyAnswer();
			$oAnswer->Set('survey_target_id', $oTarget->GetKey());
			$oAnswer->Set('question_id', $iQuestion);
			$oAnswer->Set('value', $aAnsers[$iQuestion]);
			
			list($bRes, $aIssues) = $oAnswer->CheckToWrite();
// TODO: understand why ???
			//if ($bRes)
			if (true)
	      {
				$oAnswer->DBInsertTracked($oMyChange);
			}
		}
	}

	// Update the target record
	//
	$oTarget->Set('date_response', time());
	$oTarget->Set('comment', $sComment);
	$oTarget->DBUpdateTracked($oMyChange);

	$oP->add("<p>***Your answers have been recorded.</p>\n");
	$oP->add("<p>***Thank you for your participation.</p>\n");
}

/////////////////////////////
//
// Main
//


try
{
	require_once(APPROOT.'/application/startup.inc.php');
	require_once(APPROOT.'/modules/customer-survey/quizzwebpage.class.inc.php');
	$oAppContext = new ApplicationContext();
	$sOperation = utils::ReadParam('operation', '');
	
	require_once(APPROOT.'/application/loginwebpage.class.inc.php');
//	LoginWebPage::DoLogin(false /* bMustBeAdmin */, true /* IsAllowedToPortalUsers */); // Check user rights and prompt if needed

//	$oUserOrg = GetUserOrg();
	$sCSSFileSuffix = '/modules/customer-survey/run_survey.css';
	if (@file_exists(APPROOT.$sCSSFileSuffix))
	{
//		$oP = new QuizzWebPage(Dict::S('Survey-Title'), $sCSSFileSuffix);
//		$oP->add($sCSSFileSuffix);
	}
	else
	{
//	$oP = new QuizzWebPage(Dict::S('Survey-Title'));
	}
	$oP = new QuizzWebPage(Dict::S('Survey-Title'));
// Ne fonctionne pas ????	$oP = new QuizzWebPage(Dict::S('Survey-Title'), $sCSSFileSuffix);

	$sUrl = utils::GetAbsoluteUrlAppRoot();
	$oP->set_base($sUrl.'pages/');

	$oP->add("<style>
.quizQuestion {
	border: #f1f1f6 3px solid;
	padding: 10px;
}


.quizMandatory {
	border: #f1f1f6 3px solid;
	color: red;
	padding: 10px;
}

.quizQuestion h3 {
	font-size: larger;
	font-weight: bolder;
}

.mandatory_asterisk{
	color: #FF0000;
}

textarea {
	width: 100%;
}
</style>\n");

	switch ($sOperation)
	{
	case 'submit_answers':
		$sToken = ReadMandatoryParam('token', 'raw_data');
		SubmitAnswers($oP, $sToken);
		break;
		
	case 'test':
		$iQuiz = ReadMandatoryParam('quiz_id');
		ShowDraftQuiz($oP, $iQuiz);
		break;

	default:
		$sToken = ReadMandatoryParam('token', 'raw_data');
		ShowQuiz($oP, $sToken);
	}

	$oP->output();
}
catch(CoreException $e)
{
	require_once(APPROOT.'/setup/setuppage.class.inc.php');
	$oP = new SetupWebPage(Dict::S('UI:PageTitle:FatalError'));
	$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");	
	$oP->error(Dict::Format('UI:Error_Details', $e->getHtmlDesc()));	
	$oP->output();

	if (MetaModel::IsLogEnabledIssue())
	{
		if (MetaModel::IsValidClass('EventIssue'))
		{
			try
			{
				$oLog = new EventIssue();
	
				$oLog->Set('message', $e->getMessage());
				$oLog->Set('userinfo', '');
				$oLog->Set('issue', $e->GetIssue());
				$oLog->Set('impact', 'Page could not be displayed');
				$oLog->Set('callstack', $e->getTrace());
				$oLog->Set('data', $e->getContextData());
				$oLog->DBInsertNoReload();
			}
			catch(Exception $e)
			{
				IssueLog::Error("Failed to log issue into the DB");
			}
		}

		IssueLog::Error($e->getMessage());
	}

	// For debugging only
	//throw $e;
}
catch(Exception $e)
{
	require_once(APPROOT.'/setup/setuppage.class.inc.php');
	$oP = new SetupWebPage(Dict::S('UI:PageTitle:FatalError'));
	$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");	
	$oP->error(Dict::Format('UI:Error_Details', $e->getMessage()));	
	$oP->output();

	if (MetaModel::IsLogEnabledIssue())
	{
		if (MetaModel::IsValidClass('EventIssue'))
		{
			try
			{
				$oLog = new EventIssue();
	
				$oLog->Set('message', $e->getMessage());
				$oLog->Set('userinfo', '');
				$oLog->Set('issue', 'PHP Exception');
				$oLog->Set('impact', 'Page could not be displayed');
				$oLog->Set('callstack', $e->getTrace());
				$oLog->Set('data', array());
				$oLog->DBInsertNoReload();
			}
			catch(Exception $e)
			{
				IssueLog::Error("Failed to log issue into the DB");
			}
		}

		IssueLog::Error($e->getMessage());
	}
}
?>
