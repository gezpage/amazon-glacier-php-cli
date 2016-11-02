<?php

namespace GezPage\Utility\Storage;

use Aws\Glacier\GlacierClient;
use Exception;

class Glacier
{
    private $client;

    public function __construct(GlacierClient $client)
    {
        $this->client = $client;
    }

    public function upload($vaultName, $filePath, $description)
    {
        $result = $this->client->uploadArchive([
            'vaultName'          => $vaultName,
            'archiveDescription' => $description,
            'body'               => fopen($filePath, 'r'),
        ]);

        return $result->get('archiveId');
    }

    public function listJobs($vaultName)
    {
        $result = $this->client->listJobs([
            'vaultName' => $vaultName,
        ]);

        return $result->get('JobList');
    }

    public function requestInventory($vaultName)
    {
        $result = $this->client->initiateJob([
            'vaultName' => $vaultName,
            'jobParameters' => [
                'Type' => 'inventory-retrieval',
            ],
        ]);

        $location = $result->get('location');
        $jobid    = $result->get('jobId');

        return [$location, $jobid];
    }

    public function requestJob($vaultName, $archiveId)
    {
        $result = $this->client->initiateJob([

            'vaultName' => $vaultName,

            'jobParameters' => [
                // We're telling the server that we want to retrieve an individual file.
                // If you want to retrieve all archives in a vault replace 'archive-retrieval'
                // with 'inventory-retrieval' and comment out the next line
                'Type' => 'archive-retrieval',

                // Tell Amazon the ID of the archive you want to download
                'ArchiveId' => $archiveId,
            ],
        ]);

        $location = $result->get('location');  //Relative path of the job
        $jobid    = $result->get('jobId');  //ID of the job to retrieve your data

        return [$location, $jobid];
    }

    public function downloadArchive($vaultName, $jobId, $targetFile)
    {
        $result = $this->client->getJobOutput(array(
            'vaultName' => $vaultName,
            'jobId' => $jobId,
        ));

        $data = $result->get('body');
        $description = $result->get('archiveDescription');

        if (file_exists($targetFile)) {
            throw new Exception('File already exists: ' . $targetFile);
        }

        if (!is_writable($targetFile)) {
            throw new Exception('Target file is not writable: ' . $targetFile);
        }

        $filePointer = fopen($targetFile, "w");
        fwrite($filePointer, $data);

        return $description;
    }

    public function describeVault($vaultName)
    {
        return $this->client->describeVault(array(
            //'accountId' => 'string',
            'vaultName' => $vaultName,
        ));
    }
}
