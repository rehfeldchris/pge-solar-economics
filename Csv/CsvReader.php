<?php



/**
 *
 * This class is just a semi reausable chunk of code for reading csv files. It uses generators to remain memory efficient when reading big files,
 * and does some other nice things such as reading the first column to get the column names, and then uses those names as array keys
 * when crafting the array for each row in the csv file. That way you can reference columns by name, instead of position.
 */
class CsvReader
{
    // We use a big max row length because sometimes excel exports the file with like a zillion empty
    // columns, and fgetcsv() seems to be coded to fail unless we read all cols successfully. So, we truncate very long lines.
    private $maxCharsPerCsvRow = 40000;

    protected static $BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    /**
     * @var string[]
     */
    private $columnNamesInFirstRow = [];

    public function createIterator($fileName, array $columnNameMap = null, $trimColumnNamesInFirstRow = false)
    {
        $maxCharsPerCsvRow = $this->maxCharsPerCsvRow;

        $fp = fopen($fileName, 'r');
        if (!$fp) {
            throw new Exception('io error when reading csv');
        }

        // There might be a BOM at the start of the file, which will mess up the first value of the first column
        // when we read this line as csv. We need to seek past it, so fgetcsv doesn't see/read it.
        $bom = fread($fp, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            // There's wasn't a bom, so we rewind.
            fseek($fp, 0);
        }

        // Check first row has our field names
        $firstRow = fgetcsv($fp, $maxCharsPerCsvRow);
        if (!$firstRow) {
            throw new Exception('Couldn\'t parse first row in csv');
        }

        if ($trimColumnNamesInFirstRow) {
            $firstRow = array_map('trim', $firstRow);
        }

        $this->columnNamesInFirstRow = $firstRow;

        // If they didn't specify a column name map, we use the first row to define column names.
        if ($columnNameMap === null) {
            $columnNameMap = array_combine($firstRow, $firstRow);
        }

        $missingCols = array_diff(array_keys($columnNameMap), $firstRow);
        if ($missingCols) {
            $msg = 'Some required column names weren\'t present in the first row:' . join(', ', $missingCols);
            throw new Exception($msg);
        }

        for ($line = 1; $row = fgetcsv($fp, $maxCharsPerCsvRow); $line++) {
            // Skip empty lines (lines which failed to parse, or lines with 1 column with no value)
            if (!$row || (count($row) === 1 && strlen(trim($row[0]))) === 0) {
                continue;
            }

            // Make assoc array using the column names in the csv file as keys
            $assoc = array_combine($firstRow, $row);

            // Now we map those ugly column names into the column names used in our db table
            $mapped = [];
            foreach ($columnNameMap as $csvColName => $tableColName) {
                if (!array_key_exists($csvColName, $assoc)) {
                    // this row doesnt have enough columns. the missing col is unknown, which shifts the positions of all the others.
                    // we cannot recover.
                    $msg = "A row in the csv file seemed to be missing columns. line=$line, data= " . join(', ', $row);
                    throw new Exception($msg);
                }
                $mapped[$tableColName] = $assoc[$csvColName];
            }

            yield $mapped;
        }
    }

    public function createIteratorFromString($csvData, array $columnNameMap = null, $trimColumnNamesInFirstRow = false)
    {
        $fileName = tempnam("/tmp", "CSV-TEMP-DATA");
        $bytesWritten = file_put_contents($fileName, $csvData);
        $bytesNeeded = strlen($csvData);
        if ($bytesWritten !== $bytesNeeded) {
            throw new \RuntimeException("Couldnt write all bytes to temp file for csv parsing. needed=$bytesNeeded written=$bytesWritten");
        }
        return $this->createIterator($fileName, $columnNameMap, $trimColumnNamesInFirstRow);
    }

    /**
     * You must call one of the iterator creation methods before calling this. This will return data for the most recently parsed file.
     *
     * @return \string[]
     */
    public function getColumnNamesInFirstRow()
    {
        return $this->columnNamesInFirstRow;
    }
}