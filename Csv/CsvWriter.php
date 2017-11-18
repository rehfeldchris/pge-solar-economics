<?php



class CsvWriter
{
    private $tmpDir = '/tmp';

    protected static $BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    /**
     * @param array[] $dataRows - array of assoc array
     * @param string $fileName
     * @param string[] $columnNames - if provided, these wil be written as the first line in the csv.
     * @throws CsvWriterException
     */
    public function write(array $dataRows, $fileName, array $columnNames = [])
    {

        try {
            // We initially write the file in the tmp fir. Only if we finish successfully,
            // then we rename and move the file to the data dir. This prevents partially written files from being read,
            // as well as half generated reports that failed midway.
            $tmpFileName = tempnam($this->tmpDir, $fileName);
            $fp = fopen($tmpFileName, 'w');
            if (!$fp) {
                throw new CsvWriterException("couldn't open '$tmpFileName'");
            }

            // We write utf-8 encoded data, so we put a BOM at the start of the file to hint to the csv reader program
            // that the file is utf-8 encoded.
            fputs($fp, static::$BYTE_ORDER_MARK);

            if ($columnNames) {
                $failure = false === fputcsv($fp, array_values($columnNames));
                if ($failure) {
                    throw new CsvWriterException("failed to write a csv row");
                }
            }

            foreach ($dataRows as $row) {
                $failure = false === fputcsv($fp, array_values($row));
                if ($failure) {
                    throw new CsvWriterException("failed to write a csv row");
                }
            }

            if (!fflush($fp) || !fclose($fp)) {
                throw new CsvWriterException("failed to flush fp buffer to disk");
            }

            $fp = null;

            if (!chmod($tmpFileName, 0664)) {
                throw new CsvWriterException("failed to change permissions");
            }

            if (!rename($tmpFileName, $fileName)) {
                throw new CsvWriterException("failed to rename tmp file");
            }

        } finally {
            if (!empty($fp)) {
                fclose($fp);
            }
        }
    }

    public function writeToStdOut(array $dataRows, array $columnNames = [])
    {
        // We just write to a temp file, and then read the contents out.
        // This may be a bit slower than writing straight to a stream, but this way is a bit more reliable in that a failure wont
        // output a partial csv file - it's more likely to be atomic like.
        $fileName = tempnam(sys_get_temp_dir(), 'csv-writer-temp');
        $this->write($dataRows, $fileName, $columnNames);
        readfile($fileName);
    }

    /**
     * Sends headers telling the browser to download the file, and suggests a name to save it as.
     *
     * @param array $dataRows
     * @param string $downloadFileName - should be only us ascii characters.
     * @param array $columnNames
     */
    public function writeAsHttpDownload(array $dataRows, $downloadFileName, array $columnNames = [])
    {
        header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
        $this->writeToStdOut($dataRows, $columnNames);
    }
}