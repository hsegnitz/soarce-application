<?php

namespace Soarce\Analyzer;

use mysqli;
use Slim\Container;

abstract class AbstractAnalyzer
{
    /** @var Container */
    protected $container;

    /** @var mysqli */
    protected $mysqli;

    /**
     * Usecase constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->mysqli = $this->container->mysqli;
    }

    /**
     * @param  int[] $files
     * @param  int[] $functions
     * @param  int[] $applications
     * @return array
     */
    public function getUsecases($files = [], $functions = [], $applications = []): array
    {
        $fileList        = $this->buildInStatementBody($files);
        $applicationList = $this->buildInStatementBody($applications);
        $functionList    = $this->buildInStatementBody($this->getIdsForAllFunctionCalls($functions));

        $sql = 'SELECT u.`id`, u.`name`
            FROM `usecase` u ';
        if ($fileList !== '' || $functionList !== '' || $applicationList !== '') {
            $sql .= 'JOIN `request` r on r.`usecase_id` = u.`id` '
                . ' JOIN `function_call` f ON f.`request_id` = r.`id` '
                . ($applicationList === '' ? '' : " and r.`application_id` in ({$applicationList}) ")
                . ($fileList        === '' ? '' : " and f.`file_id`        in ({$fileList}) ")
                . ($functionList    === '' ? '' : " and f.`id`             in ({$functionList}) ");
        }

        $sql .= ' WHERE 1 GROUP BY u.`id` ORDER BY u.`name` ASC';
        $ret = [];
        $result = $this->mysqli->query($sql);

        if (!$result) {
            throw new AnalyzerException($this->mysqli->error, $this->mysqli->errno);
        }

        while ($row = $result->fetch_assoc()) {
            $ret[$row['id']] = $row;
        }
        return $ret;
    }

    /**
     * @param  int[] $usecases
     * @return array
     */
    public function getAppplications($usecases = []): array
    {
        $usecaseList = $this->buildInStatementBody($usecases);

        $sql = 'SELECT a.`id`, a.`name`
            FROM `application` a
            ' . ($usecaseList !== '' ? "JOIN `request` r on r.`application_id` = a.id and r.`usecase_id` in ({$usecaseList}) " : '') . '
            WHERE 1 GROUP BY a.`id` ORDER BY a.`name` ASC';
        $ret = [];
        $result = $this->mysqli->query($sql);

        if (!$result) {
            throw new AnalyzerException($this->mysqli->error, $this->mysqli->errno);
        }

        while ($row = $result->fetch_assoc()) {
            $ret[$row['id']] = $row;
        }
        return $ret;
    }

    /**
     * @param  int[] $usecases
     * @param  int[] $applications
     * @param  int[] $files
     * @return array
     */
    public function getRequests($usecases = [], $applications = [], $files = []): array
    {
        $applicationList = $this->buildInStatementBody($applications);
        $usecaseList     = $this->buildInStatementBody($usecases);
        $fileList        = $this->buildInStatementBody($files);

        $sql = 'SELECT r.`id`, r.`request_id` as `name`
            FROM `request` r
            JOIN `application` a on r.`application_id` = a.`id` ' . ($applicationList !== '' ? " and r.`application_id` in ({$applicationList}) " : '')
                                                                  . ($usecaseList     !== '' ? " and r.`usecase_id`     in ({$usecaseList})     " : '') . '
            JOIN `coverage` c    ON c.`request_id`     = r.`id` ' . ($fileList        !== '' ? " and c.`file_id`        in ({$fileList})        " : '') . '
            WHERE 1 GROUP BY r.`id` ORDER BY `name` ASC';
        $ret = [];
        $result = $this->mysqli->query($sql);

        if (!$result) {
            throw new AnalyzerException($this->mysqli->error, $this->mysqli->errno);
        }

        while ($row = $result->fetch_assoc()) {
            $ret[$row['id']] = $row;
        }
        return $ret;
    }

    /**
     * @param  int[]|int $ids
     * @return string
     */
    protected function buildInStatementBody($ids): string
    {
        if (!is_array($ids)) {
            return (string)$ids;
        }

        array_walk($ids, 'intval');
        return implode(',', $ids);
    }

    /**
     * @param  int[] $selectedFunctionIds
     * @return int[]
     */
    private function getIdsForAllFunctionCalls($selectedFunctionIds = []): array
    {
        if ($selectedFunctionIds === []) {
            return [];
        }

        $functionList = $this->buildInStatementBody($selectedFunctionIds);
        $sql = "SELECT f.`class`, f.`function` FROM `function_call` f WHERE f.`id` IN ({$functionList});";

        $result = $this->mysqli->query($sql);
        if (!$result) {
            throw new AnalyzerException($this->mysqli->error, $this->mysqli->errno);
        }

        $selects = [];
        while ($row = $result->fetch_assoc()) {
            $className    = mysqli_real_escape_string($this->mysqli, $row['class']);
            $functionName = mysqli_real_escape_string($this->mysqli, $row['function']);
            $selects[] = "SELECT `id` FROM `function_call` WHERE `class` = '{$className}' AND `function` = '{$functionName}' ";
        }

        $sql = implode(' UNION ', $selects);

        $result = $this->mysqli->query($sql);
        if (!$result) {
            throw new AnalyzerException($this->mysqli->error, $this->mysqli->errno);
        }

        $res = $result->fetch_all(MYSQLI_ASSOC);
        return array_column($res, 'id');
    }
}
