<?php
/**
******************************************************************************************
**   @version    4.0.0                                                                  **
**   @package    com_joomgallery                                                        **
**   @author     JoomGallery::ProjectTeam <team@joomgalleryfriends.net>                 **
**   @copyright  2008 - 2022  JoomGallery::ProjectTeam                                  **
**   @license    GNU General Public License version 2 or later                          **
*****************************************************************************************/

namespace Joomgallery\Component\Joomgallery\Administrator\Service\Filesystem;

// No direct access
\defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\Filesystem\File as JFile;
use \Joomla\CMS\Filesystem\Path as JPath;

use \Joomla\Component\Media\Administrator\Model\ApiModel;
use \Joomgallery\Component\Joomgallery\Administrator\Service\Filesystem\FilesystemInterface;
use \Joomgallery\Component\Joomgallery\Administrator\Extension\ServiceTrait;
use \Joomgallery\Component\Joomgallery\Administrator\Helper\JoomHelper;

/**
* Filesystem Base Class
*
* @package JoomGallery
*
* @since  4.0.0
*/
class Filesystem extends ApiModel implements FilesystemInterface
{
  use ServiceTrait;

  /**
   * The adapter name.
   * Scheme: adapter-rootfolder
   *
   * @var   string
   * @since  4.0.0
   */
  protected $filesystem = 'local-images';

  /**
   * The available extensions.
   *
   * @var   string[]
   * @since  4.0.0
   */
  private $allowedExtensions = null;

  /**
   * Root folder of the local filesystem
   *
   * @var string
   */
  protected $local_root = JPATH_ROOT;

  /**
   * Constructor
   *
   * @param  string  $filesystem  Name of the filesystem to use
   *
   * @return  void
   *
   * @since   4.0.0
   */
  public function __construct(string $filesystem='')
  {
    parent::__construct();
    
    if($filesystem != '')
    {
      $this->filesystem = $filesystem;
    }
  }

  /**
   * Function to strip additional / or \ in a path name.
   *
   * @param   string  $path   The path to clean
   * @param   string  $ds     Directory separator (optional)
   *
   * @return  string  The cleaned path
   *
   * @since   4.0.0
   */
  public function cleanPath($path, $ds=\DIRECTORY_SEPARATOR): string
  {
    return JPath::clean($path, $ds);
  }

  /**
   * Cleaning of file/category name
   * optionally replace extension if present
   * replace special chars defined in the configuration
   *
   * @param   string    $file            The file name
   * @param   integer   $with_ext        0: strip extension, 1: force extension, 2: leave it as it is (default: 2)
   * @param   string    $use_ext         Extension to use if $file given without extension
   * @param   string    $replace_chars   Characters to be replaced
   *
   * @return  mixed     cleaned name on success, false otherwise
   *
   * @since   1.0.0
   */
  public function cleanFilename($file, $with_ext=2, $use_ext='jpg', $replace_chars='')
  {
    // Check if multibyte support installed
    if(\in_array ('mbstring', \get_loaded_extensions()))
    {
      // Get the funcs from mb
      $funcs = \get_extension_funcs('mbstring');
      if(\in_array ('mb_detect_encoding', $funcs) && \in_array ('mb_strtolower', $funcs))
      {
        // Try to check if the name contains UTF-8 characters
        $isUTF = \mb_detect_encoding($file, 'UTF-8', true);
        if($isUTF)
        {
          // Try to lower the UTF-8 characters
          $file = \mb_strtolower($file, 'UTF-8');
        }
        else
        {
          // Try to lower the one byte characters
          $file = \strtolower($file);
        }
      }
      else
      {
        // TODO mbstring loaded but no needed functions
        // --> server misconfiguration
        $file = \strtolower($file);
      }
    }
    else
    {
      // TODO no mbstring loaded, appropriate server for Joomla?
      $file = \strtolower($file);
    }

    // Replace special chars
    $filenamesearch  = array();
    $filenamereplace = array();

    $items = \explode(',', $replace_chars);
    if($items != false)
    {
      // Contains pairs of <specialchar>|<replaced char(s)>
      foreach($items as $item)
      {
        if(!empty($item))
        {
          $workarray = \explode('|', \trim($item));
          if($workarray != false && isset($workarray[0]) && !empty($workarray[0]) && isset($workarray[1]) && !empty($workarray[1]))
          {
            \array_push($filenamesearch, \preg_quote($workarray[0]));
            \array_push($filenamereplace, \preg_quote($workarray[1]));
          }
        }
      }
    }

    // Replace whitespace with underscore
    \array_push($filenamesearch, '\s');
    \array_push($filenamereplace, '_');
    // Replace slash with underscore
    \array_push($filenamesearch, '/');
    \array_push($filenamereplace, '_');
    // Replace backslash with underscore
    \array_push($filenamesearch, '\\\\');
    \array_push($filenamereplace, '_');
    // Replace other stuff
    \array_push($filenamesearch, '[^a-z_0-9-]');
    \array_push($filenamereplace, '');

    // Checks for different array-length
    $lengthsearch  = \count($filenamesearch);
    $lengthreplace = \count($filenamereplace);
    if($lengthsearch > $lengthreplace)
    {
      while($lengthsearch > $lengthreplace)
      {
        \array_push($filenamereplace, '');
        $lengthreplace = $lengthreplace + 1;
      }
    }
    else
    {
      if($lengthreplace > $lengthsearch)
      {
        while($lengthreplace > $lengthsearch)
        {
          \array_push($filenamesearch, '');
          $lengthsearch = $lengthsearch + 1;
        }
      }
    }

    $detect_ext = JFile::getExt($file);

    // Replace extension if present
    if($detect_ext)
    {
      $fileextensionlength  = \strlen($detect_ext);
      $filenamelength       = \strlen($file);
      $filename             = \substr($file, -$filenamelength, -$fileextensionlength - 1);
    }
    else
    {
      // No extension found (Batchupload)
      $filename = $file;
    }

    // Perform the replace
    for($i = 0; $i < $lengthreplace; $i++)
    {
      $searchstring = '!'.$filenamesearch[$i].'+!i';
      $filename     = \preg_replace($searchstring, $filenamereplace[$i], $filename);
    }

    switch($with_ext)
    {
      case 0:
        // strip extension
        break;

      case 1:
        // add extension
        if($detect_ext)
        {
          $filename = $filename.'.'. \strtolower($detect_ext);
        }
        else
        {
          $filename = $filename.'.'. \strtolower($use_ext);
        }
        break;
      
      default:
        // leave it as it is
        if($detect_ext)
        {
          $filename = $filename.'.'. \strtolower($detect_ext);
        }
        break;
    }

    return $filename;
  }

  /**
   * Check filename if it's valid for the filesystem
   *
   * @param   string    $nameb          filename before any processing
   * @param   string    $namea          filename after processing in e.g. fixFilename
   * @param   bool      $checkspecial   True if the filename shall be checked for special characters only
   *
   * @return  bool      True if the filename is valid, false otherwise
   *
   * @since   2.0.0
  */
  public function checkFilename($nameb, $namea = '', $checkspecial = false): bool
  {
    // TODO delete this function and the call of them?
    return true;

    // Check only for special characters
    if($checkspecial)
    {
      $pattern = '/[^0-9A-Za-z -_]/';
      $check = \preg_match($pattern, $nameb);
      if($check == 0)
      {
        // No special characters found
        return true;
      }
      else
      {
        return false;
      }
    }
    // Remove extension from names
    $nameb = JFile::stripExt($nameb);
    $namea = JFile::stripExt($namea);

    // Check the old filename for containing only underscores
    if(\strlen($nameb) - \substr_count($nameb, '_') == 0)
    {
      $nameb_onlyus = true;
    }
    else
    {
      $nameb_onlyus = false;
    }
    if(empty($namea) || (!$nameb_onlyus && strlen($namea) == substr_count($nameb, '_')))
    {
      return false;
    }
    else
    {
      return true;
    }
  }

  /**
   * Copies an index.html file into a specified folder
   *
   * @param   string   $path    The path where the index.html should be created
   * 
   * @return  bool     True on success, false otherwise
   * 
   * @since   4.0.0
   */
  public function createIndexHtml($path): bool
  {
    // Content
    $html = '<html><body bgcolor="#FFFFFF"></body></html>';
    // File path
    $file = JPath::clean($path.\DIRECTORY_SEPARATOR.'index.html');

    return \file_put_contents($file, $html);
  }

  /**
   * Sets the permission of a given file or folder recursively.
   *
   * @param   string  $path      The path to the file/folder
   * @param   string  $val       The octal representation of the value to change file/folder mode
   * @param   bool    $mode      True to use file mode. False to use folder mode. (default: true)
   *
   * @return  bool    True if successful [one fail means the whole operation failed].
   *
   * @since   4.0.0
   */
  public function chmod($path, $val, $mode=true): bool
  {
    // complete folder path
    $path = $this->completePath($path);

    if($mode)
    {
      return JPath::setPermissions(JPath::clean($path), $val, null);
    }
    else
    {
      return JPath::setPermissions(JPath::clean($path), null, $val);
    }
  }

  /**
   * Checks if the given path is an allowed file.
   *
   * @param   string  $path  The path to file
   *
   * @return boolean
   *
   * @since   4.0.0
   */
  private function isMediaFile($path)
  {
      // Check if there is an extension available
      if(!strrpos($path, '.'))
      {
          return false;
      }

      // Initialize the allowed extensions
      if ($this->allowedExtensions === null)
      {
          // Get options from the input or fallback to images only
          $mediaTypes = [];
          $types      = [];
          $extensions = [];

          // Default to showing all supported formats
          if(count($mediaTypes) === 0)
          {
              $mediaTypes = ['0', '1', '2', '3'];
          }

          array_map(
              function ($mediaType) use (&$types) {
                  switch ($mediaType) {
                      case '0':
                          $types[] = 'images';
                          break;
                      case '1':
                          $types[] = 'audios';
                          break;
                      case '2':
                          $types[] = 'videos';
                          break;
                      case '3':
                          $types[] = 'documents';
                          break;
                      default:
                          break;
                  }
              },
              $mediaTypes
          );

          $images = array_map(
              'trim',
              explode(',',$this->component->getConfig()->get('jg_filetypes','jpg,jpeg,png,gif,webp'))
          );

          foreach($types as $type)
          {
              if(in_array($type, ['images', 'audios', 'videos', 'documents']))
              {
                  $extensions = array_merge($extensions, ${$type});
              }
          }

          // Make them an array
          $this->allowedExtensions = $extensions;
      }

      // Extract the extension
      $extension = strtolower(substr($path, strrpos($path, '.') + 1));

      // Check if the extension exists in the allowed extensions
      return in_array($extension, $this->allowedExtensions);
  }

  /**
   * Get the filesystem property.
   *
   * @return string  The filesystem
   *
   * @since   4.0.0
   */
  public function getFilesystem(): string
  {
    return $this->filesystem;
  }
}
