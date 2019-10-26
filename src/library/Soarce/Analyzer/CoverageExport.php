<?php

namespace Soarce\Analyzer;

class CoverageExport extends AbstractAnalyzer
{
    /**
     * @param  string  $applicationName
     * @return int[][]
     */
    public function getCoverage($applicationName): array
    {
        $files = $this->getFiles($applicationName);

        $sql = 'SELECT c.`line`, MAX(c.`covered`) as `coverageLevel`
            FROM `coverage` c
            WHERE c.`file_id` IN (' . implode(',', array_keys($files)) . ')
            GROUP BY c.`file_id`, c.`line`
            ORDER BY c.`file_id`, c.`line`
            ';

        $result = $this->mysqli->query($sql);

        if (!$result) {
            throw new AnalyzerException($this->mysqli->error, $this->mysqli->errno);
        }

        $ret = [];
        while ($row = $result->fetch_assoc()) {
            $ret[$row['line']] = $row['coverageLevel'];
        }

        return $ret;
    }

    /**
     * @param  string $applicationName
     * @return array
     */
    private function getFiles($applicationName): array
    {
        $sql = 'SELECT f.`id` as `fileId`, f.`filename` as `fileName`,
            FROM `file` f
            WHERE f.`application_id` IN (SELECT `a`.`id` FROM `application` as `a`  WHERE `a`.`name` = "'
            .  mysqli_real_escape_string($this->mysqli, $applicationName)
            . '")';
        $result = $this->mysqli->query($sql);

        if (!$result) {
            throw new AnalyzerException($this->mysqli->error, $this->mysqli->errno);
        }

        if ($result->num_rows > 0) {
            $res = $result->fetch_all(MYSQLI_ASSOC);
            return array_column($res, 'fileName', 'fileId');
        }

        return [];
    }

}
