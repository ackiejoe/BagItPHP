<?php

require_once 'lib/bagit.php';

/**
 * Recursively delete a directory.
 */
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir . "/" . $object) == "dir")
                    rrmdir($dir . "/" . $object);
                else
                    unlink($dir . "/" . $object);
            }
        }
        reset($objects);
        rmdir($dir);
    }
}

/**
 * Get a temporary name and create a directory there.
 */
function tmpdir($prefix='bag') {
    $dir = tempnam(sys_get_temp_dir(), $prefix);
    unlink($dir);
    return $dir;
}

/**
 * This abuses the unit test framework to do some use case testing.
 */
class BagPhpUseCaseTest extends PHPUnit_Framework_TestCase {
    var $to_rm;

    private function queueRm($dirname)
    {
        array_push($this->to_rm, $dirname);
    }

    public function setUp()
    {
        $this->to_rm = array();
    }

    public function tearDown()
    {
        foreach ($this->to_rm as $dirname)
        {
            rrmdir($dirname);
        }
    }

    /**
     * This is a use case for creating and populating a new bag. The user
     * does these actions:
     *
     * <ol>
     * <li>Create a new bag;</li>
     * <li>Add files to the bag;</li>
     * <li>Add fetch entries;</li>
     * <li>Update the bag; and</li>
     * <li>Package the bag.</li>
     * </ol>
     */
    public function testBagProducer()
    {
        $tmpdir = tmpdir();
        mkdir($tmpdir);
        $this->queueRm($tmpdir);

        $tmpbag = "$tmpdir/BagProducer";

        // 1. Create a new bag;
        $bag = new BagIt($tmpbag);

        $this->assertTrue($bag->isValid());
        $this->assertTrue($bag->isExtended());

        $bagInfo = $bag->getBagInfo();
        $this->assertEquals('0.96',  $bagInfo['version']);
        $this->assertEquals('UTF-8', $bagInfo['encoding']);
        $this->assertEquals('sha1',  $bagInfo['hash']);

        $this->assertEquals("$tmpbag/data", $bag->getDataDirectory());
        $this->assertEquals('sha1', $bag->getHashEncoding());
        $this->assertEquals(0, count($bag->getBagContents()));
        $this->assertEquals(0, count($bag->getBagErrors()));

        // 2. Add files to the bag;
        $srcdir = __DIR__ . '/TestBag/data';
        copy("$srcdir/README.txt", "{$bag->dataDirectory}/README.txt");

        mkdir("{$bag->dataDirectory}/payloads");
        copy(
            "$srcdir/imgs/uvalib.png",
            "{$bag->dataDirectory}/payloads/uvalib.png"
        );
        copy(
            "$srcdir/imgs/fibtriangle-110x110.jpg",
            "{$bag->dataDirectory}/payloads/fibtri.jpg"
        );

        // 3. Add fetch entries;
        $bag->addFetchEntries(
            array(array('http://www.scholarslab.org/', 'data/index.html'))
        );

        // 4. Update the bag; and
        $bag->update();

        // 5. Package the bag.
        $pkgfile = "$tmpdir/BagProducer.tgz";
        $bag->package($pkgfile);

        // Finally, we need to validate the contents of the package.
        $dest = new BagIt($pkgfile);
        // $this->queueRm($dest->bagDirectory);

        // First, verify that the data files are correct.
        $this->assertEquals(
            "BagIt-Version: 0.96\n" .
            "Tag-File-Character-Encoding: UTF-8\n",
            file_get_contents($dest->bagitFile)
        );

        // Second, verify that everything was uncompressed OK.
        $dest->validate();
        $this->assertEquals(0, count($dest->bagErrors));

        // Now, check that the file was fetched.
        $dest->fetch();
        $this->assertFileExists("{$dest->bagDirectory}/data/index.html");

    }

    /**
     * This is the use case for consuming a bag from someone else. The user 
     * does these actions:
     *
     * <ol>
     * <li>Open the bag;</li>
     * <li>Fetch on-line items in the bag;</li>
     * <li>Validate the bag's contents; and</li>
     * <li>Copy items from the bag onto the local disk.</li>
     * </ol>
     */
    public function testBagConsumer()
    {
        $this->fail();
    }

}

?>