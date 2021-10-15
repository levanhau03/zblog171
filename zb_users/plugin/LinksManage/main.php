<?php
require '../../../zb_system/function/c_system_base.php';
require '../../../zb_system/function/c_system_admin.php';
$zbp->Load();
$action = 'root';
if (!$zbp->CheckRights($action)) {
  $zbp->ShowError(6);
  die();
}
if (!$zbp->CheckPlugin('LinksManage')) {
  $zbp->ShowError(48);
  die();
}
InstallPlugin_LinksManage();
$act = GetVars('act', 'GET');
$suc = GetVars('suc', 'GET');
if (GetVars('act', 'GET') == 'save') {
  if (function_exists('CheckIsRefererValid')) {
    CheckIsRefererValid();
  }
  // todo
  // GetModuleByFileName 判断是否重复
  $mod = $zbp->GetModuleByID(GetVars('ID', 'POST'));
  // 解析表单内容为数组
  $sub = 0;
  $tree = (int) $_POST['tree'] == 1;
  $items = array();
  $parent = null;
  foreach ($_POST['text'] as $k => $v) {
    $item = (object) array();
    if ($k == count($_POST['text']) - 1) {
      continue;
    }
    $item->href = $_POST['href'][$k];
    $item->href = str_replace($zbp->host, "{#ZC_BLOG_HOST#}", $item->href);
    $item->ico = $_POST['ico'][$k];
    $item->title = $_POST['title'][$k];
    $item->target = (bool) $_POST['target'][$k] ? '_blank' : '';
    $item->text = trim($_POST['text'][$k]);
    $item->subs = array();
    $item->issub = 0;
    if ($k > 0 && $_POST['sub'][$k]) {
      $item->issub = 1;
      $parent->subs[$item->text] = $item;
    } else {
      $items[$item->text] = $item;
      $parent = &$items[$item->text];
    }
  }
  // 转为JSON后存至Meta
  $mod->Metas->LM_json = json_encode($items);
  // 生成content
  $fileName = GetVars('FileName', 'POST');
  $mod->Content = LinksManage_GenModCon($items, $fileName);
  // 其他字段写入
  $mod->Name = $_POST['Name'];
  if ($mod->ID == 0) {
    $mod->FileName = $fileName;
  }
  $mod->HtmlID = $_POST['HtmlID'];
  $mod->Source = $_POST['Source'];
  $mod->IsHideTitle = (int) $_POST['IsHideTitle'];
  $mod->Type = 'ul';
  $mod->MaxLi = 0;
  FilterModule($mod);
  // 保存并更新缓存
  $mod->Save();
  $zbp->SetHint('good');
  Redirect($_POST['stay'] == '1' ? $_SERVER['HTTP_REFERER'] : '../../../zb_system/cmd.php?act=ModuleMng');
}

$mod = new Module();
$mod->ID = 0;
$mod->Source = 'plugin_LinksManage';

// 新链接表单项
$outTpl = "lm-module-admin";
$zbp->template->SetTags('item', LinksManage_GetNewItem());
$new_tr = $list = $zbp->template->Output($outTpl);
$new_tr = str_replace('<tr class="">', '<tr class="LinksManageAdd">', $new_tr);

// $list = '<tr><td><input type="text" name="href[]" value="http://" size="30" /></td><td><input type="text" name="ico[]" value="" size="15" /></td><td><input type="text" name="title[]" value="Mô tả liên kết" size="30" /></td><td><input type="text" name="name[]" value="Tên mới" size="20" /></td><td><input type="text" name="target[]" class="checkbox" value="0" /></td><td><input type="text" name="sub[]" class="checkbox" value="0" /></td></tr>';

$islock = '';
$tree = null;
$delbtn = '';

if ($edit = GetVars('edit', 'GET')) {
  if (!empty($edit)) {
    $mod = $zbp->modulesbyfilename[$edit];
  }
  $file_contents = $mod->Metas->LM_json;
  if (strlen($file_contents) > 0 && $items = json_decode($file_contents)) {
    $list = '';
    foreach ($items as $item) {
      $item->subs = (array) $item->subs;
      $zbp->template->SetTags('item', $item);
      $list .= $zbp->template->Output($outTpl);
    }
  } else {
    $content = $mod->Content;
    preg_match('/<\/ul><\/li>/i', $content, $tree);
    if ($tree) {
      $content = str_replace(array('<ul>', '</ul></li>'), array("</li>\n", ''), $content);
    }
    $preg = array(
      'tag'    => '/<li.*?<\/li>/',
      'sub'    => '/<li.*?class=[\'|\"](.*?)[\'|\"]/i',
      'href'   => '/<a.*?href=[\'|\"](.*?)[\'|\"]/i',
      'target' => '/<a.*?target=[\'|\"](.*?)[\'|\"]/i',
      'text'   => '/<a.*?>(.*?)<\/a>/i', 'title' => '/<a.*?title=[\'|\"](.*?)[\'|\"]/i',
    );
    $link = array();
    preg_match_all($preg['tag'], $content, $tag);
    foreach ($tag[0] as $key => $val) {
      foreach ($preg as $k => $v) {
        preg_match($v, $val, $m);
        if (count($m) > 1) {
          if ($k == 'text') {
            $m[1] = preg_replace('/<img.*?[\/]>/i', '', $m[1]);
          }
          if ($k == 'sub') {
            $m[1] = !preg_match(
              '/sub/i',
              $m[1]
            ) ? '' : 'LinksManageSub';
          }
          $link[$k][$key] = $m[1];
        } else {
          $link[$k][$key] = '';
        }
      }
    }
    if ($link) {
      $list = '';
      foreach ($link['tag'] as $k => $v) {
        $list .= '<tr class="' . $link['sub'][$k] . '">
          <td><input name="href[]" value="' . $link['href'][$k] . '" size="30" /></td>
          <td><input name="title[]" value="' . $link['title'][$k] . '" size="20" /></td>
          <td><input name="text[]" value="' . $link['text'][$k] . '" size="20" /></td>
          <td><input name="target[]" value="' . ($link['target'][$k] ? 1 : 0) . '" class="checkbox" /></td>
          <td><input name="sub[]" value="' . ($link['sub'][$k] ? 1 : 0) . '" class="checkbox" /></td>
          <td><input name="ico[]" value="" size="15" /></td>
        <tr>';
      }
    }
  }
  if ($mod->Source == 'system' || $mod->Source == 'theme' || $mod->FileName !== "") {
    $islock = 'readonly="readonly"';
  }
  $delbtn = $mod->Source === 'plugin_LinksManage' ? '&nbsp;<a title="Xóa mô-đun hiện tại"
    onclick="return window.confirm(\'' . $zbp->lang['msg']['confirm_operating'] . '\');"
    href="' . BuildSafeCmdURL('act=ModuleDel&amp;source=theme&amp;filename=' . $mod->FileName) . '"><img
      src="' . $zbp->host . 'zb_system/image/admin/delete.png" alt="Xóa" title="Xóa" width="16"></a>' : '';
  $bakFile = LinksManage_Path("bakdir") . "{$mod->FileName}.txt";
  if (is_file($bakFile)) {
    $bakUrl = str_replace($zbp->path, $zbp->host, $bakFile);
  }
}

$blogtitle = 'Quản lý liên kết';
$blogtitle .= $mod->Name !== "" ? "（{$mod->Name}）" : "";
require $blogpath . 'zb_system/admin/admin_header.php';
require $blogpath . 'zb_system/admin/admin_top.php';
?>
<div id="divMain">
  <div class="divHeader"><?php echo $blogtitle; ?></div>
  <div class="SubMenu">
  </div>
  <div id="divMain2">
    <form id="edit" name="edit" method="post" action="<?php echo BuildSafeURL('main.php?act=save'); ?>">
      <input name="ID" type="hidden" value="<?php echo $mod->ID; ?>" />
      <input name="Source" type="hidden" value="<?php echo $mod->Source; ?>" />
      <table class="tableFull tableBorder tableBorder-thcenter">
        <thead>
          <tr>
            <th>Liên kết</th>
            <th class="td10">Mô tả</th>
            <th class="td10">Tiêu đề</th>
            <th class="td5"><abbr title="Mở trong một cửa sổ mới">Cửa sổ</abbr></th>
            <th class="td5"><abbr title="Là một liên kết phụ của liên kết chính trước đó">Cấp 2</abbr></th>
            <th>Biểu tượng</th>
          </tr>
        </thead>
        <tbody id="LinksManageList">
          <?php echo $list; ?>
        </tbody>
        <tfoot>
          <tr id="LinksManageAdd">
            <td colspan="6" class="tdCenter"><input type="button" class="button js-add" value="Thêm mục"> <input type="button" class="js-search" value="Tìm kiếm danh mục / trang"> Các mục hiện có có thể được kéo để sắp xếp hoặc xóa</td>
          </tr>
          <tr id="LinksManageDel">
            <td colspan="6" class="tdCenter">Kéo vào đây để xóa</td>
          </tr>
          <!-- tr class="LinksManageAdd" -->
          <?php echo $new_tr; ?>
          <!-- .LinksManageAdd End -->
        </tfoot>
      </table>
      <table class="tableFull tableBorder tableBorder-thcenter">
        <tr>
          <th><?php echo $lang['msg']['name'] ?>(Logo ngắn gọn)</th>
          <th><?php echo $lang['msg']['filename'] ?>(có thể đặt tên tệp)</th>
          <th><?php echo $lang['msg']['htmlid'] ?>(Để trống để sử dụng tên tệp)</th>
          <th class="td10"><?php echo $lang['msg']['hide_title'] ?></th>
          <th class="td10"><?php echo $lang['msg']['del'] ?></th>
          <th class="td10 hidden"><abbr title="Đóng cây để sử dụng định dạng lồng nhau, nghĩa là menu phụ bị ẩn theo mặc định">Hình cây[?]</abbr></th>
        </tr>
        <tr>
          <td><input id="edtName" size="20" name="Name" maxlength="50" type="text" value="<?php echo $mod->Name; ?>" /></td>
          <td><input id="edtFileName" size="20" name="FileName" type="text" value="<?php echo $mod->FileName; ?>" <?php echo $islock ?> /></td>
          <td><input id="edtHtmlID" size="20" name="HtmlID" type="text" value="<?php echo $mod->HtmlID; ?>" />
          </td>
          <td class="tdCenter"><input type="text" id="IsHideTitle" name="IsHideTitle" class="checkbox" value="<?php echo $mod->IsHideTitle; ?>" /></td>
          <td class="tdCenter"><?php echo $delbtn ?></td>
          <td class="hidden"><input type="text" name="tree" class="checkbox" value="<?php echo $tree ? 0 : 1; ?>" /></td>
        </tr>
      </table>
      <p>
        <input type="submit" class="button" value="<?php echo $lang['msg']['submit'] ?>" onclick="return checkInfo();" />
        <input type="text" name="stay" class="checkbox" value="1" /> Trở lại trang này sau khi gửi
        <?php if (isset($bakUrl)) { ?>
          <a title="Xem bản sao lưu" href="<?php echo $bakUrl; ?>" target="_blank">Xem bản sao lưu（<?php echo $mod->FileName; ?>）</a>
          [Khi bạn tắt plugin quản lý liên kết, nó sẽ được khôi phục vào mô-đun, nhưng cấu hình trên sẽ không bị mất]
        <?php } ?>
      </p>
      ------
      <p>Đối với mỗi li, "file name-item" sẽ được thêm làm tên lớp theo mặc định, hiện tại: <?php echo "{$mod->FileName}-item"; ?></p>
      <p>Tác giả chủ đề có thể đặt mẫu <b>lm-module-<?php echo "{$mod->FileName}"; ?></b> để tùy chỉnh mô-đun hiện tại</p>
      <p>Tham khảo: zb_users/plugin/LinksManage/var/li.html</p>
      <p>Mẫu chung tùy chỉnh: zb_users/plugin/LinksManage/usr/li.html (Không được khuyến khích)</p>
      <p>Mẫu chung được biên dịch dưới dạng <b>lm-module-defend</b></p>
    </form>
  </div>
</div>

<?php echo file_get_contents(LinksManage_Path("tpl-search"));?>
<?php
require $blogpath . 'zb_system/admin/admin_footer.php';
RunTime();
?>
