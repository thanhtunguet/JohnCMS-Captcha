<?php

namespace uet\JohnCMS {

    use Closure;
    use ReflectionClass;
    use ReflectionException;

    class Captcha
    {
        /**
         * Hash Algorithm Constants
         */
        const MD5_HASHING = 10;
        const SHA1_HASHING = 11;
        const MIXED_MD5_SHA1 = 1011;
        const MIXED_SHA1_MD5 = 1110;
        const REVERSE_KEY = -10;
        const NONE = 0;

        /**
         * @var resource $image
         */
        protected $image = NULL;

        /**
         * @var string $credits
         */
        protected $credits = 'JohnCMS';

        /**
         * @var integer $jpegQuality
         */
        protected $jpegQuality = 98;

        /**
         * @var string $fontsDirectory
         */
        protected $fontsDirectory = '';
        /**
         * @var string $keyString
         */
        protected $keyString = '';

        /**
         * Captcha constructor.
         */
        public function __construct()
        {
            $fontsDirectory = $this->getFontsDirectoryPath();
            $this->setFontsDirectory($fontsDirectory);
        }

        /**
         * Get the real path of fonts directory
         */
        protected function getFontsDirectoryPath()
        {
            $reflectionClass = NULL;

            try {
                $reflectionClass = new ReflectionClass(self::class);
            } catch (ReflectionException $exception) {
                die($exception->getMessage());
            }

            $filename = $reflectionClass->getFileName();
            $filepath = dirname($filename);
            $fontsDirectory = $filepath . '/../fonts';
            return realpath($fontsDirectory);
        }

        /**
         * @param bool $return
         * @return null|resource
         */
        public function create($return = FALSE)
        {
            $alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';
            $allowed_symbols = '0123456789abcdef';

            $length = mt_rand(6, 8);

            $width = 120;
            $height = 50;

            $fluctuation_amplitude = 5;

            $no_spaces = TRUE;

            if ($this->credits) {
                $show_credits = TRUE;
            } else {
                $show_credits = FALSE;
            }

            $credits = $this->credits;

            $foreground_color = array(
                mt_rand(0, 100),
                mt_rand(0, 100),
                mt_rand(0, 100)
            );

            $background_color = array(
                mt_rand(200, 255),
                mt_rand(200, 255),
                mt_rand(200, 255)
            );

            ////////////////////////////////////////////////////////////
            $fonts = array();

            $fontDir_absolute = $this->fontsDirectory;

            if (($handle = opendir($fontDir_absolute)) !== FALSE) {
                while (FALSE !== ($file = readdir($handle))) {
                    if (preg_match('/\.png$/i', $file)) {
                        $fonts[] = $fontDir_absolute . '/' . $file;
                    }
                }
                closedir($handle);
            }

            $alphabet_length = strlen($alphabet);

            do {
                // generating random keyString
                while (TRUE) {
                    $this->keyString = '';
                    for ($i = 0; $i < $length; $i++) {
                        $this->keyString .= $allowed_symbols{mt_rand(0, strlen($allowed_symbols) - 1)};
                    }
                    if (!preg_match('/cp|cb|ck|c6|c9|rn|rm|mm|co|do|cl|db|qp|qb|dp|ww/', $this->keyString))
                        break;
                }
                $font_file = $fonts[mt_rand(0, count($fonts) - 1)];
                $font = imagecreatefrompng($font_file);
                imageAlphaBlending($font, TRUE);
                $fontfile_width = imagesx($font);
                $fontfile_height = imagesy($font) - 1;
                $font_metrics = array();
                $symbol = 0;
                $reading_symbol = FALSE;
                // loading font
                for ($i = 0; $i < $fontfile_width && $symbol < $alphabet_length; $i++) {
                    $transparent = (imagecolorat($font, $i, 0) >> 24) == 127;
                    if (!$reading_symbol && !$transparent) {
                        $font_metrics[$alphabet{$symbol}] = array('start' => $i);
                        $reading_symbol = TRUE;
                        continue;
                    }
                    if ($reading_symbol && $transparent) {
                        $font_metrics[$alphabet{$symbol}]['end'] = $i;
                        $reading_symbol = FALSE;
                        $symbol++;
                        continue;
                    }
                }
                $img = imageCreateTrueColor($width, $height);
                imageAlphaBlending($img, TRUE);
                $white = imageColorAllocate($img, 255, 255, 255);
                imageFilledRectangle($img, 0, 0, $width - 1, $height - 1, $white);
                // draw text
                $x = 1;
                for ($i = 0; $i < $length; $i++) {
                    $m = $font_metrics[$this->keyString{$i}];
                    $y = mt_rand(-$fluctuation_amplitude, $fluctuation_amplitude) + ($height - $fontfile_height) / 2 + 2;
                    if ($no_spaces) {
                        $shift = 0;
                        if ($i > 0) {
                            $shift = 10000;
                            for ($sy = 7; $sy < $fontfile_height - 20; $sy += 1) {
                                for ($sx = $m['start'] - 1; $sx < $m['end']; $sx += 1) {
                                    $rgb = imagecolorat($font, $sx, $sy);
                                    $opacity = $rgb >> 24;
                                    if ($opacity < 127) {
                                        $left = $sx - $m['start'] + $x;
                                        $py = $sy + $y;
                                        if ($py > $height)
                                            break;
                                        for ($px = min($left, $width - 1); $px > $left - 12 && $px >= 0; $px -= 1) {
                                            $color = imagecolorat($img, $px, $py) & 0xff;
                                            if ($color + $opacity < 190) {
                                                if ($shift > $left - $px) {
                                                    $shift = $left - $px;
                                                }
                                                break;
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                            if ($shift == 10000) {
                                $shift = mt_rand(4, 6);
                            }
                        }
                    } else {
                        $shift = 1;
                    }
                    imagecopy($img, $font, $x - $shift, $y, $m['start'], 1, $m['end'] - $m['start'], $fontfile_height);
                    $x += $m['end'] - $m['start'] - $shift;
                }
            } while ($x >= $width - 10); // while not fit in canvas
            $center = $x / 2;
            // credits. To remove, see configuration file
            $this->image = imageCreateTrueColor($width, $height + ($show_credits ? 12 : 0));
            $foreground = imageColorAllocate($this->image, $foreground_color[0], $foreground_color[1], $foreground_color[2]);
            $background = imageColorAllocate($this->image, $background_color[0], $background_color[1], $background_color[2]);
            imageFilledRectangle($this->image, 0, 0, $width - 1, $height - 1, $background);
            imageFilledRectangle($this->image, 0, $height, $width - 1, $height + 12, $foreground);
            $credits = empty($credits) ? $_SERVER['HTTP_HOST'] : $credits;
            imagestring($this->image, 2, $width / 2 - imagefontwidth(2) * strlen($credits) / 2, $height - 2, $credits, $background);
            // periods
            $rand1 = mt_rand(750000, 1200000) / 10000000;
            $rand2 = mt_rand(750000, 1200000) / 10000000;
            $rand3 = mt_rand(750000, 1200000) / 10000000;
            $rand4 = mt_rand(750000, 1200000) / 10000000;
            // phases
            $rand5 = mt_rand(0, 31415926) / 10000000;
            $rand6 = mt_rand(0, 31415926) / 10000000;
            $rand7 = mt_rand(0, 31415926) / 10000000;
            $rand8 = mt_rand(0, 31415926) / 10000000;
            // amplitudes
            $rand9 = mt_rand(330, 420) / 110;
            $rand10 = mt_rand(330, 450) / 110;

            //wave distortion
            for ($x = 0; $x < $width; $x++) {
                for ($y = 0; $y < $height; $y++) {

                    $sx = $x + (sin($x * $rand1 + $rand5) + sin($y * $rand3 + $rand6)) * $rand9 - $width / 2 + $center + 1;
                    $sy = $y + (sin($x * $rand2 + $rand7) + sin($y * $rand4 + $rand8)) * $rand10;

                    if ($sx < 0 || $sy < 0 || $sx >= $width - 1 || $sy >= $height - 1) {
                        continue;
                    } else {

                        $color = imagecolorat($img, $sx, $sy) & 0xFF;
                        $color_x = imagecolorat($img, $sx + 1, $sy) & 0xFF;
                        $color_y = imagecolorat($img, $sx, $sy + 1) & 0xFF;
                        $color_xy = imagecolorat($img, $sx + 1, $sy + 1) & 0xFF;
                    }

                    if ($color == 255 && $color_x == 255 && $color_y == 255 && $color_xy == 255) {
                        continue;

                    } else if ($color == 0 && $color_x == 0 && $color_y == 0 && $color_xy == 0) {
                        $newRed = $foreground_color[0];
                        $newGreen = $foreground_color[1];
                        $newBlue = $foreground_color[2];

                    } else {
                        $frsx = $sx - floor($sx);
                        $frsy = $sy - floor($sy);
                        $frsx1 = 1 - $frsx;
                        $frsy1 = 1 - $frsy;

                        $newColor = ($color * $frsx1 * $frsy1 + $color_x * $frsx * $frsy1 + $color_y * $frsx1 * $frsy + $color_xy * $frsx * $frsy);
                        if ($newColor > 255)
                            $newColor = 255;
                        $newColor = $newColor / 255;
                        $newColor0 = 1 - $newColor;
                        $newRed = $newColor0 * $foreground_color[0] + $newColor * $background_color[0];
                        $newGreen = $newColor0 * $foreground_color[1] + $newColor * $background_color[1];
                        $newBlue = $newColor0 * $foreground_color[2] + $newColor * $background_color[2];
                    }
                    imagesetpixel($this->image, $x, $y, imageColorAllocate($this->image, $newRed, $newGreen, $newBlue));
                }
            }

            return ($return ? $this->image : NULL);
        }

        /**
         * Get keyString after hashing or reversing
         *
         * @param   integer $hashing
         * @return  string
         */
        public function getKeyString($hashing = self::NONE)
        {
            $keyString = $this->keyString;

            switch ($hashing) {

                case self::MD5_HASHING:
                    $keyString = md5($keyString);
                    break;

                case self::SHA1_HASHING:
                    $keyString = sha1($keyString);
                    break;

                case self::MIXED_MD5_SHA1:
                    $keyString = sha1($keyString);
                    $keyString = md5($keyString);
                    break;

                case self::MIXED_SHA1_MD5:
                    $keyString = md5($keyString);
                    $keyString = sha1($keyString);
                    break;

                case self::REVERSE_KEY:
                    $keyString = strrev($keyString);
                    break;

                case self::NONE:
                default:

                    break;
            }

            return $keyString;
        }

        /**
         * Output image
         *
         * @param int $type
         */
        public function output($type = IMAGETYPE_PNG)
        {
            ob_start();

            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Cache-Control: post-check=0, pre-check=0', FALSE);
            header('Pragma: no-cache');

            // Output file by image type
            switch ($type) {

                case IMAGETYPE_PNG:
                    imagePng($this->image);
                    break;

                case IMAGETYPE_GIF:
                    imageGif($this->image);
                    break;

                case IMAGETYPE_JPEG:
                default:
                    imageJpeg($this->image, null, $this->jpegQuality);
                    break;
            }

            // Set MIME Type for Image
            $mime_type = image_type_to_mime_type($type);
            header("Content-Type: {$mime_type}");

            // Set the output filename
            $filename = md5($_SERVER['REQUEST_TIME']);
            $filename = substr($filename, 5, 10);
            $filename .= image_type_to_extension($type, TRUE);

            // Set Content-Disposition
            header("Content-Disposition: inline; filename={$filename}");

            // Send file size in Header
            $length = ob_get_length();
            header("Content-Length: {$length}");

            ob_end_flush();
        }

        /**
         * @return resource
         */
        public function getImage()
        {
            return $this->image;
        }

        /**
         * @return string
         */
        public function getCredits()
        {
            return $this->credits;
        }

        /**
         * @param string $credits
         */
        public function setCredits($credits)
        {
            $this->credits = $credits;
        }

        /**
         * @return int
         */
        public function getJpegQuality()
        {
            return $this->jpegQuality;
        }

        /**
         * @param int $jpegQuality
         */
        public function setJpegQuality($jpegQuality)
        {
            $this->jpegQuality = $jpegQuality;
        }

        /**
         * @return string
         */
        public function getFontsDirectory()
        {
            return $this->fontsDirectory;
        }

        /**
         * @param string $fontsDirectory
         */
        public function setFontsDirectory($fontsDirectory)
        {
            $this->fontsDirectory = $fontsDirectory;
        }

        /**
         * Pass as argument of Closure handler
         *
         * @param Closure $closure
         * @return mixed
         */
        public function closure(Closure $closure)
        {
            return $closure($this->image);
        }
    }
}
