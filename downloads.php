<? include('header.php'); ?>
  <h2>Downloads</h2>

  The following links will take you to the SourceForge download web site to download the latest release. Most users will want the .tar.gz file, but Debian Linux users (including Ubuntu users) may find the .deb package more convenient.

  <h2>Latest Release</h2>

  <blockquote>2.2.1 - <a href="http://downloads.sourceforge.net/vufind/vufind-2.2.1.tar.gz?use_mirror=osdn">tar.gz</a>, <a href="http://downloads.sourceforge.net/vufind/vufind-2.2.1.zip?use_mirror=osdn">zip</a> (all platforms), <a href="http://downloads.sourceforge.net/vufind/vufind_2.2.1.deb?use_mirror=osdn">deb</a> (Debian Linux), <a href="https://github.com/vufind-org/vufind/tree/v2.2.1">browse code</a></blockquote>

  <h2>Development Version</h2>
  <h4>Current Development</h4>

  You can also get the latest 2.x code from our GitHub Repository. Development code is not guaranteed to be 100% stable, but it may contain features not yet available in official releases.

  <blockquote>git clone <a href="https://github.com/vufind-org/vufind.git">https://github.com/vufind-org/vufind.git</a></blockquote>

  To learn more about Git, see our <a href="https://vufind.org/wiki/vufind2:git">Git documentation</a>. This is recommended reading if you plan on making significant customizations to the software!

  <h4>Legacy Development</h4>

  The VuFind 1.x code is no longer under active development, but it is still available in the <a href="http://sourceforge.net/projects/vufind/?source=directory">SourceForge Subversion Repository</a>. It is not recommended that you use this code, but occasional bug fixes may still be committed for the benefit of users still dependent upon the legacy version.

  <blockquote>svn export <a href="https://sourceforge.net/p/vufind/svn/HEAD/tree/trunk/">svn://svn.code.sf.net/p/vufind/svn/trunk/</a> /usr/local/vufind</blockquote>

  To learn more about Subversion, see our <a href="http://vufind.org/wiki/subversion">Subversion documentation</a>. This is recommended reading if you plan on making significant customizations to the software!

  <h4>Release Archive</h4>
  <table class="table">
    <?
      $versions = array(
        '2.2' => array('tar','zip*','deb','github'),
        '2.1.1' => array('tar','zip*','deb','github'),
        '2.1' => array('tar','zip*','deb','github'),
        '2.0.1' => array('tar','zip*','deb','github'),
        '2.0' => array('tar','zip*','deb','github'),
        '2.0RC1' => array('tar','zip*','deb','github'),
        '2.0beta' => array('tar','zip*','deb','github'),
        '2.0alpha' => array('tar'),
        '1.4' => array('tar*','deb'),
        '1.3' => array('tar*','deb'),
        '1.2' => array('tar*','deb'),
        '1.1' => array('tar*','deb'),
        '1.0.1' => array('tar*','deb'),
        '1.0' => array('tar*','deb'),
        '1.0RC2' => array('tar'),
        '1.0RC1' => array('tar'),
        '0.8.2' => array('tar'),
        '0.7' => array('tar'),
        '0.6.1' => array('tar'),
        '0.5' => array('tar')
      );
    ?>
    <? foreach($versions as $ver => $types): ?>
      <tr><th><?=str_replace('alpha', ' Alpha', str_replace('beta', ' Beta', str_replace('RC', ' Release Candidate ', $ver))) ?></th>
      <td>
      <? foreach($types as $i=>$type): ?>
        <? if(strstr($type, '*')): ?>
          <? $type = substr($type, 0, -1); ?>
          <? $all = true; ?>
        <? else: ?>
          <? $all = false; ?>
        <? endif;
        switch($type) {
          case 'tar':
            echo '<a href="http://downloads.sourceforge.net/vufind/vufind-'.$ver.'.tar.gz?use_mirror=osdn">tar.gz</a>';
            break;
          case 'zip':
            echo '<a href="http://downloads.sourceforge.net/vufind/vufind-'.$ver.'.zip?use_mirror=osdn">zip</a>';
            break;
          case 'deb':
            echo '<a href="http://downloads.sourceforge.net/vufind/vufind-'.$ver.'.deb?use_mirror=osdn">deb</a>';
            break;
          case 'github':
            echo '<a href="https://github.com/vufind-org/vufind/tree/v'.$ver.'">browse code</a> (GitHub)';
            break;
        } ?>
        <? if($all): ?> (all platforms)<? endif; ?>
        <? if($i < count($types)-1): ?>, <? endif; ?>
      <? endforeach; ?>
      </td>
    <? endforeach; ?>
  </table>
<? include('footer.php'); ?>