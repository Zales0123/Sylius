<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\ImportExportBundle\Behat;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use EasyCSV\Writer;
use Sylius\Component\ImportExport\Model\ExportJobInterface;
use Sylius\Component\ImportExport\Model\ExportProfileInterface;
use Sylius\Component\ImportExport\Model\ImportJobInterface;
use Sylius\Component\ImportExport\Model\ImportProfileInterface;
use Sylius\Component\ImportExport\Model\ProfileInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Process\Process;
use Sylius\Bundle\ResourceBundle\Behat\DefaultContext;

/**
 * ImportExportContext for ImportExport scenarios
 *
 * @author Mateusz Zalewski <mateusz.zalewski@lakion.com>
 */
class ImportExportContext extends DefaultContext
{
    /**
     * @var Process
     */
    private $process;
    /**
     * @var string
     */
    private $processOutput;
    /**
     * @var string
     */
    private $filePath;

    /**
     */
    public function __construct($applicationName = null)
    {
        parent::__construct($applicationName);
    }

    /**
     * @AfterScenario @scenarioWithFile
     */
    public function unlinkFile(AfterScenarioScope $scope)
    {
        if (null !== $this->filePath && file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }

    /**
     * @Given there are following export profiles configured:
     * @And there are following export profiles configured:
     */
    public function thereAreExportProfiles(TableNode $table)
    {
        $manager = $this->getEntityManager();
        $repository = $this->getRepository('export_profile');

        foreach ($table->getHash() as $data) {
            $this->thereIsExportProfile(
                $data['name'],
                $data['description'],
                $data["code"],
                $data['reader'],
                $data['reader_configuration'],
                $data['writer'],
                $data['writer_configuration'],
                false
            );
        }

        $manager->flush();
    }

    /**
     * @Given there are following import profiles configured:
     * @And there are following import profiles configured:
     */
    public function thereAreImportProfiles(TableNode $table)
    {
        $manager = $this->getEntityManager();
        $repository = $this->getRepository('import_profile');

        foreach ($table->getHash() as $data) {
            $this->thereIsImportProfile(
                $data['name'],
                $data['description'],
                $data["code"],
                $data['reader'],
                $data['reader configuration'],
                $data['writer'],
                $data['writer configuration'],
                false
            );
        }

        $manager->flush();
    }

    /**
     * @Given there are following export jobs set:
     * @And there are following export jobs set:
     */
    public function thereAreExportJobs(TableNode $table)
    {
        $manager = $this->getEntityManager();

        foreach ($table->getHash() as $data) {
            $this->thereIsExportJob($data['status'], $data['start_time'], $data["end_time"], $data['created_at'], $data['updated_at'], $data["export_profile"], false);
        }

        $manager->flush();
    }

    /**
     * @Given I am on the export jobs index page for profile with code :code
     * @Then I should be on the export jobs index page for profile with code :code
     */
    public function iShouldBeOnTheExportJobsIndexPageForProfileWithCode($code)
    {
        $exportProfile = $this->findOneBy('export_profile', array('code' => $code));

        $this->getSession()->visit($this->generatePageUrl('sylius_backend_export_job_index', array('profileId' => $exportProfile->getId())));
    }

    /**
     * @When I press :button near :number export job
     * @When I click :button near :number export job
     */
    public function iPressNearExportJob($button, $number)
    {
        $tr = $this->assertSession()->elementExists('css', sprintf('table tbody tr:nth-child(%s)', $number));

        $locator = sprintf('button:contains("%s")', $button);

        if ($tr->has('css', $locator)) {
            $tr->find('css', $locator)->press();
        } else {
            $tr->clickLink($button);
        }
    }

    /**
     * @When I run :command command in less then :seconds seconds
     */
    public function iRunCommand($command, $seconds)
    {
        $this->process = new Process($this->constructCommand($command));
        $this->process->setTimeout($seconds);
        try {
            $this->process->mustRun();
        } catch(\Exception $e) {
            $this->processOutput = $e->getMessage();
        }
    }

    /**
     * @Then I should find :filePath file
     * @When The file at path :filePath exists
     */
    public function iShouldFindFile($filePath)
    {
        $this->filePath = $filePath;

        if (!file_exists($filePath)) {
            $errorMessage = sprintf('File at path %s does not exist', $filePath);
            throw new \Exception($errorMessage);
        }
    }

    /**
     * @Then this file should contains :rowsNumber rows
     */
    public function andThisFileShouldContainsRows($rowsNumber)
    {
        $lineCount = count(file($this->filePath)) - 1;

        if ((int)$rowsNumber !== $lineCount) {
            $errorMessage = sprintf('File contains %s instead of %s lines', $lineCount, $rowsNumber);

            throw new \Exception($errorMessage);
        }
    }

    /**
     * @Then the command should finish successfully
     */
    public function theCommandShouldFinishSuccessfully()
    {
        if (!$this->process->isSuccessful()) {
            throw new \Exception('Command finish unsuccessfully! Finished with message '.$this->processOutput);
        }
    }

    /**
     * @Then I should see :message error in terminal
     */
    public function iShouldSeeErrorInTerminal($message)
    {
        $this->areMessagesIdentical($message,  $this->process->getErrorOutput());
    }

    /**
     * @Then I should see :message in terminal
     */
    public function iShouldSeeInTerminal($message)
    {
        $this->areMessagesIdentical($message, $this->process->getOutput());
    }

    /**
     * @Then the command should finish unsuccessfully
     */
    public function theCommandShouldFinishUnsuccessfully()
    {
        if ($this->process->isSuccessful()) throw new \Exception('Command finish successfully!');
    }

    /**
     * @Then I should find :numberOfJobs :jobType job for this :profileType profile in database
     */
    public function iShouldFindJobForThisProfileInDatabase($numberOfJobs, $jobStatus, $profileType)
    {
        $repository = $this->getRepository($profileType.'_job');
        $numberOfJobsInDatabase = count($repository->findBy(array('status' => $jobStatus)));

        if ((int)$numberOfJobs !== $numberOfJobsInDatabase) {
            $executionMessage = sprintf('Expected was %d jobs with status "%s" in database, but %d was found',
                $numberOfJobs,
                $jobStatus,
                $numberOfJobsInDatabase
            );

            throw new \Exception($executionMessage);
        }
    }

    /**
     * @Then I should see  :message in error message
     */
    public function iShouldFindInErrorMessage($message)
    {
        if (false === strpos($this->processOutput, $message)) {
            $executionMessage = sprintf('"%s" was not found in error message "%s"',
                $message,
                $this->processOutput
            );

            throw new \Exception($executionMessage);
        }
    }

    /**
     * @Given there are following import jobs set:
     */
    public function thereAreFollowingImportJobsSet(TableNode $table)
    {
        $manager = $this->getEntityManager();

        foreach ($table->getHash() as $data) {
            $this->thereIsImportJob($data['status'], $data['start_time'], $data["end_time"], $data['created_at'], $data['updated_at'], $data["import_profile"], false);
        }

        $manager->flush();
    }

    /**
     * @Given I am on the import jobs index page for profile with code :code
     * @And I am on the import jobs index page for profile with code :code
     */
    public function iAmOnTheImportJobsIndexPageForProfileWithCode($code)
    {
        $importProfile = $this->findOneBy('import_profile', array('code' => $code));

        $this->getSession()->visit($this->generatePageUrl('sylius_backend_import_job_index', array('profileId' => $importProfile->getId())));
    }

    /**
     * @Given I press :button near :value import job
     * @Given I click :button near :value import job
     */
    public function iPressNearImportJob($button, $value)
    {
        $tr = $this->assertSession()->elementExists('css', sprintf('table tbody tr:nth-child(%s)', $value));

        $locator = sprintf('button:contains("%s")', $button);

        if ($tr->has('css', $locator)) {
            $tr->find('css', $locator)->press();

            return;
        }

        $tr->clickLink($button);
    }


    /**
     * @Then I should be on the import jobs index page for profile with code :code
     */
    public function iShouldBeOnTheImportJobsIndexPageForProfileWithCode($code)
    {
        $importProfile = $this->findOneBy('import_profile', array('code' => $code));

        $this->getSession()->visit($this->generatePageUrl('sylius_backend_import_job_index', array('profileId' => $importProfile->getId())));
    }

    /**
     * @Given there are following users put into a file :path:
     */
    public function thereAreFollowingUsersPutIntoAFile($path, TableNode $table)
    {
        $rawUsers = array();

        $this->filePath = $path;

        foreach ($table->getHash() as $data) {
            $rawUsers[] = $this->createRawUser(
                $data['email'],
                isset($data['password']) ? $data['password'] : $this->faker->word(),
                array('ROLE_USER'),
                isset($data['enabled']) ? $data['enabled'] : true
            );
        }

        $csvWriter = new Writer($path, 'w');
        $csvWriter->setDelimiter(';');
        $csvWriter->setEnclosure('"');
        $csvWriter->writeRow(array_keys($rawUsers[0]));
        $csvWriter->writeFromArray($rawUsers);
    }

    /**
     * @Then the file data should be valid
     */
    public function theFileDataShouldBeValid()
    {
        $headers = fgets(fopen($this->filePath, 'r'));

        if (false === strpos($headers, 'customerEmail') ||
            false === strpos($headers, 'customerFirstName') ||
            false === strpos($headers, 'customerLastName') ||
            false === strpos($headers, 'username') ||
            false === strpos($headers, 'roles')
        ) {
            throw new \Exception('The file is not valid');
        }
    }

    /**
     * @Then I should have :numberOfUsers users in a database
     */
    public function iHaveMoreUsersInADatabase($numberOfUsers)
    {
        $repository = $this->getRepository('user');

        $numberOfUsersInDatabase = count($repository->findAll());
        if ((int) $numberOfUsers !== $numberOfUsersInDatabase){
            $message = sprintf(
                '%s users was expected to be found, but %s was found in a database',
                $numberOfUsers,
                $numberOfUsersInDatabase
            );

            throw new \Exception($message);
        }
    }

    /**
     * @param string    $name
     * @param string    $description
     * @param string    $code
     * @param string    $reader
     * @param string    $readerConfiguration
     * @param string    $writer
     * @param string    $writerConfiguration
     * @param bool|true $flush
     *
     * @return ImportProfileInterface
     */
    private function thereIsImportProfile(
        $name,
        $description,
        $code,
        $reader,
        $readerConfiguration,
        $writer,
        $writerConfiguration,
        $flush = true
    )
    {
        $repository = $this->getRepository('import_profile');
        return $this->createProfileInDatabase($repository, $description, $code, $reader, $readerConfiguration, $writer, $writerConfiguration, $name, $flush);
    }

    /**
     * @param string    $name
     * @param string    $description
     * @param string    $code
     * @param string    $reader
     * @param string    $readerConfiguration
     * @param string    $writer
     * @param string    $writerConfiguration
     * @param bool|true $flush
     *
     * @return ExportProfileInterface
     */
    private function thereIsExportProfile(
        $name,
        $description,
        $code,
        $reader,
        $readerConfiguration,
        $writer,
        $writerConfiguration,
        $flush = true
    )
    {
        $repository = $this->getRepository('export_profile');
        return $this->createProfileInDatabase($repository, $description, $code, $reader, $readerConfiguration, $writer, $writerConfiguration, $name, $flush);
    }

    /**
     * @param string    $status
     * @param string    $startTime
     * @param string    $endTime
     * @param string    $createdAt
     * @param string    $updatedAt
     * @param string    $exportProfileCode
     * @param bool|true $flush
     *
     * @return ExportJobInterface
     */
    private function thereIsExportJob($status, $startTime, $endTime, $createdAt, $updatedAt, $exportProfileCode, $flush = true)
    {
        $repository = $this->getRepository('export_job');
        $exportJob = $repository->createNew();
        $exportJob->setStatus($status);
        $exportJob->setStartTime(new \DateTime($startTime));
        $exportJob->setEndTime(new \DateTime($endTime));
        $exportJob->setCreatedAt(new \DateTime($createdAt));
        $exportJob->setUpdatedAt(new \DateTime($updatedAt));

        $exportProfile = $this->getRepository('export_profile')->findOneByCode($exportProfileCode);
        $exportJob->setProfile($exportProfile);

        $manager = $this->getEntityManager();
        $manager->persist($exportJob);

        if ($flush) {
            $manager->flush();
        }

        return $exportJob;
    }

    /**
     * @param string    $status
     * @param string    $startTime
     * @param string    $endTime
     * @param string    $createdAt
     * @param string    $updatedAt
     * @param string    $importProfileCode
     * @param bool|true $flush
     *
     * @return ImportJobInterface
     */
    private function thereIsImportJob($status, $startTime, $endTime, $createdAt, $updatedAt, $importProfileCode, $flush = true)
    {
        $repository = $this->getRepository('import_job');
        $importJob = $repository->createNew();
        $importJob->setStatus($status);
        $importJob->setStartTime(new \DateTime($startTime));
        $importJob->setEndTime(new \DateTime($endTime));
        $importJob->setCreatedAt(new \DateTime($createdAt));
        $importJob->setUpdatedAt(new \DateTime($updatedAt));

        $importProfile = $this->getRepository('import_profile')->findOneByCode($importProfileCode);
        $importJob->setProfile($importProfile);

        $manager = $this->getEntityManager();
        $manager->persist($importJob);

        if ($flush) {
            $manager->flush();
        }

        return $importJob;
    }

    /**
     * @param string $firstMessage
     * @param string $secondMessage
     *
     * @throws \Exception
     */
    private function areMessagesIdentical($firstMessage, $secondMessage)
    {
        if ($firstMessage !== $secondMessage) {
            $executionMessage = sprintf('"%s" is not identical with "%s"',
                $firstMessage,
                $secondMessage
            );

            throw new \Exception($executionMessage);
        }
    }

    /**
     * @param string $command
     *
     * @return string
     */
    private function constructCommand($command)
    {
        return sprintf('%s/console %s --env test', $this->getContainer()->getParameter('kernel.root_dir'), $command);
    }

    /**
     * @param RepositoryInterface $repository
     * @param string              $description
     * @param string              $code
     * @param string              $reader
     * @param string              $readerConfiguration
     * @param string              $writer
     * @param string              $writerConfiguration
     * @param string              $name
     * @param string              $flush
     *
     * @return ProfileInterface
     */
    private function createProfileInDatabase($repository, $description, $code, $reader, $readerConfiguration, $writer, $writerConfiguration, $name, $flush)
    {
        $profile = $repository->createNew();
        $profile->setName($name);
        $profile->setDescription($description);
        $profile->setCode($code);

        $profile->setReader($reader);

        $readerConfiguration = $this->getConfiguration($readerConfiguration);
        $readerConfiguration["headers"] = isset($writerConfiguration["headers"]) ? (bool) $writerConfiguration["Headers"] : true;

        $profile->setReaderConfiguration($readerConfiguration);

        $profile->setWriter($writer);
        $profile->setWriterConfiguration($this->getConfiguration($writerConfiguration));

        $manager = $this->getEntityManager();
        $manager->persist($profile);

        if ($flush) {
            $manager->flush();
        }

        return $profile;
    }

    /**
     * @param string $email
     * @param string $password
     * @param array  $role
     * @param string $enabled
     * @param array  $groups
     * @param array  $authorizationRoles
     * @param string $createdAt
     *
     * @return array
     */
    private function createRawUser($email, $password, array $role = null, $enabled = true)
    {
        return array(
            'username' => $email,
            'usernameCanonical' => $email,
            'customerEmail' => $email,
            'customerEmailCanonical' => $email,
            'customerFirstName' => $this->faker->firstName,
            'customerLastName' => $this->faker->lastName,
            'enabled' => $enabled,
            'plainPassword' => $password,
            'roles' => json_encode($role),
            'createdAt' => '2015-07-24 09:37:51',
            'updatedAt' => '2015-07-24 09:37:51',
            'id' => 1836,
        );
    }
}
