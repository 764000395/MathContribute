<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller {
	public function __construct() {
		parent::__construct();
		$this->load->model('index_model');
	}

	/************************ 前端内容 Begin ************************/
	//首页
	public function index() {
		$data['link'] = $this->index_model->get_link_list();
		$this->load->view('index/index.html', $data);
	}

	//栏目内容页
	public function content($col_id) {
		if (!is_numeric($col_id)) {
			alert_msg('您访问的内容不存在！');
		}

		//获取栏目信息
		$pid = $this->index_model->get_col_info(array('col_id' => $col_id));
		if (empty($pid)) {
			alert_msg('您访问的内容不存在！');
		}
		$data['this_col_name'] = $pid[0]['col_name'];
		//判断是否为1级栏目
		if ($pid[0]['pid'] != 0) {
			$data['col'] = $this->index_model->get_col_info(array('pid' => $pid[0]['pid']));
		} else {
			$data['col'] = $pid;
		}

		//该栏目内容
		$content = $this->index_model->get_content_info(array('col_id' => $col_id));
		if (empty($content)) {
			alert_msg('您访问的信息不存在');
		}
		$data['content'] = $content[0];
		$this->load->view('index/content.html', $data);
	}
	/************************ 前端内容 End ************************/

	/************************ 前台业务逻辑 Begin ************************/
	/*
		注册 register
	 */
	public function register($action = '', $identity = 'author') {
		if ($action == 'do') {
			$password = $this->input->post('pwd');
			$re_password = $this->input->post('re_pwd');
			//判断两次密码输入是否一致
			if ($password != $re_password) {
				$array = array(
					'code' => 400,
					'message' => '两次输入密码不一致',
				);
				$this->_get_type($array);
			}

			//检查邮箱是否已经被注册
			$email = $this->input->post('email');
			$isset_email = $this->db->select('user_id')->get_where('user', array('email' => $email))->result_array();
			if (!empty($isset_email)) {
				$array = array(
					'code' => 400,
					'message' => '该邮箱已经注册，请直接登录',
				);
				$this->_get_type($array);
			}
			$register_info = array(
				'email' => $email,
				'password' => md5($password),
				'realname' => $this->input->post('realname'),
				'sex' => $this->input->post('sex'),
				'major' => $this->input->post('major'),
				'research_direction' => $this->input->post('research_direction'),
				'address' => $this->input->post('address'),
				'phone' => $this->input->post('phone'),
				'postcode' => $this->input->post('postcode'),
				'organization' => $this->input->post('organization'),
				'qq' => $this->input->post('qq'),
				'edu_background' => $this->input->post('edu_background'),
				'identity' => $this->input->post('identity'),
			);

			//如果注册的用户不是作者，将用户状态设置为0，需要通过后台管理员审核后才可使用
			$register_info['status'] = $register_info['identity'] == 'author' ? 1 : 0;
			$status = $this->db->insert('user', $register_info);
			if ($status) {
				$this->session->set_userdata(array('user_id' => $status, 'email' => $email, 'identity' => $register_info['identity']));
				$array = array(
					'code' => 200,
					'message' => '注册成功',
				);
			} else {
				$array = array(
					'code' => 400,
					'message' => '注册失败，请检查信息是否输入正确',
				);
			}
			$this->_get_type($array);
		} else {
			$data['identity'] = $identity;
			$this->load->view('index/register.html', $data);
		}

	}

	/*
		登录接口
	 */
	public function login_api() {
		$authcode = $this->session->userdata('user_authcode');
		$post_authcode = $this->input->post('authcode');
		if (empty($post_authcode) || $authcode != $post_authcode) {
			$array = array(
				'code' => 400,
				'message' => '验证码错误',
			);
			$this->_get_type($array);
		}
		$email = $this->input->post('email');
		$password = $this->input->post('password');
		$status = $this->index_model->get_user_info($email, $password);
		if (empty($status)) {
			$array = array(
				'code' => 400,
				'message' => '用户名或密码不正确',
			);
			$this->_get_type($array);
		} else {
			$user_session = array(
				'user_id' => $status[0]['user_id'],
				'realname' => $status[0]['realname'],
				'identity' => $status[0]['identity'],
			);
			$this->session->set_userdata($user_session);
			$array = array(
				'code' => 200,
				'message' => '登录成功',
			);
			$this->_get_type($array);
		}
	}

	/*
		登陆界面
	 */
	public function login() {
		$this->load->view('index/login.html');
	}

	/*
		检查登录状态
	 */
	public function login_test($identity, $type = '') {
		if ($identity == $this->session->userdata('identity')) {
			if ($type == 'ajax') {
				$data = array(
					'code' => 200,
					'message' => '请先登录！',
				);
				$this->_get_type($data);
			} else {
				header('location:' . site_url('index/myhome'));
			}
		} else {
			if ($type == 'ajax') {
				$data = array(
					'code' => 400,
					'message' => '请先登录！',
				);
				$this->_get_type($data);
			} else {
				header('location:' . site_url('home/login'));
			}
		}
	}
	/*
		忘记密码
	 */
	public function forget_password($action = 'see') {
		if ($action == 'do') {
			$post_email_authcode = $this->input->post('email_authcode');

			//判断邮箱验证码是否输入正确
			if ($post_email_authcode != $this->session->userdata('email_authcode')) {
				$array = array(
					'code' => 400,
					'message' => '邮箱验证码输入错误，请仔细查看！',
				);
				$this->_get_type($array);
			}

			$new_password = $this->input->post('password');
			$re_password = $this->input->post('re_password');

			//判断两次密码是否输入一致
			if ($new_password != $re_password) {
				$array = array(
					'code' => 400,
					'message' => '两次输入密码不一致',
				);
				$this->_get_type($array);
			}

			//执行修改密码操作
			$status = $this->db->update('user', array('password' => md5($new_password)), array('user_id' => $this->session->userdata('user_id')));
			if ($status) {
				$array = array(
					'code' => 200,
					'message' => '密码修改成功，请登录！',
				);
				$this->session->sess_destory();
			} else {
				$array = array(
					'code' => 400,
					'message' => '密码修改失败，请稍后重试！',
				);
			}
			$this->_get_type($array);
		} else {
			$this->load->view('index/forget_password.html');
		}
	}

	/*
		获取邮箱验证码
	 */
	public function get_email_authcode() {
		$post_authcode = $this->input->post('authcode');
		$authcode = $this->session->userdata('user_authcode');
		//验证码是否输入正确
		if ($post_authcode != $authcode) {
			$array = array(
				'code' => 400,
				'message' => '您输入的验证码不正确',
			);
			$this->_get_type($array);
		}

		//限制邮箱验证码3分钟发一次
		if (!empty($this->session->tempdata('sended_email'))) {
			$array = array(
				'code' => 400,
				'message' => '邮箱验证码已发送，请注意查收。',
			);
			$this->_get_type($array);
		}
		//邮箱是否存在
		$email = $this->input->post('email');
		$status = $this->db->select('user_id, realname')->get_where('user', array('email' => $email))->result_array();
		if (empty($status)) {
			$array = array(
				'code' => 400,
				'message' => '您输入的邮箱不正确！',
			);
			$this->_get_type($array);
		}

		//发送邮箱验证码
		$email_authcode = mt_rand(100000, 999999);

		$subject = '邮箱验证码——数学季刊投稿系统';
		$message = '尊敬的' . $status[0]['realname'] . '您好，你正在进行修改登录密码操作，您的邮箱验证码为：' . $email_authcode . ' 请勿将此验证码发送给任何人。';
		$this->load->library('myclass');
		if ($this->myclass->send_email($email, $subject, $message)) {
			$this->session->set_userdata(array('user_id' => $status[0]['user_id'], 'email_authcode' => $email_authcode));
			$this->session->set_tempdata('sended_email', '1', 180);
			$array = array(
				'code' => 200,
				'message' => '邮箱验证码已发送！',
			);
		} else {
			$array = array(
				'code' => 400,
				'message' => '邮箱验证码发送失败，请稍后重试！',
			);
		}
		$this->_get_type($array);
	}

	/*
		下载稿件
	*/
	public function download($article_id, $action = '') {
		//article_id必须为数字，防止sql注入
		if (!is_numeric($article_id)) {
			alert_msg('无法进行下载！', 'close');
		}
		$where_arr = array('article_id' => $article_id);
		//判断是否从用户中心发来的下载请求
		if ($action == 'authority') {
			$where_arr['user_id'] = $this->session->userdata('user_id');
			$authority = false; //与check_status相或，取消判断稿件状态
		} else {
			$authority = true;
		}

		//判断稿件是否存在
		$article = $this->db->select('title, check_status, attachment_url')->get_where('article', $where_arr)->result_array();
		if (empty($article)) {
			alert_msg('无法完成下载！', 'close');
		}

		//如果用户不是专家或者编委，不能下载未经过编委会审核的稿件
		//0=未审核 1=初审完成 2=二审完成 3=编委会定稿完成 -1=拒稿
		$identity = $this->session->userdata('identity');

		if ($authority && $identity != 'specialist' && $identity != 'editorial' && $article[0]['check_status'] < 3) {
			alert_msg('无法完成下载！', 'close');
		}

		//执行下载
		$this->load->helper('download');
		$data = file_get_contents($this->config->item('MYPATH') . $article[0]['attachment_url']);

		//匹配文件后缀名，重命名下载
		preg_match('/.\w+$/', $article[0]['attachment_url'], $matches);
		force_download($article[0]['title'] . $matches[0], $data);
	}

	public function ceshi() {
		$status = 1;
		$status && print_r('短路与，前面status为1，执行输出');
		echo '<br>';
		!$status && print_r('前面status为0,不执行输出');
		echo '<br>';
		$status || print_r('短路或，前面status为1，整个表达式值为1，后面不在执行');
		echo '<br>';
		!$status || print_r('前面status为0, 继续向后执行看后面是否成立，所以执行后面输出</br>');
		$this->db->select('*')->get_where('user', array())->result_array();
		echo $this->db->last_query();
	}
	/*
		验证码
	*/
	public function authcode() {
		if (!isset($_SESSION)) {
			session_start();
		}
		$img = imagecreatetruecolor(100, 40);
		$bgcolor = imagecolorallocate($img, rand(200, 255), rand(200, 255), rand(200, 255));
		imagefill($img, 0, 0, $bgcolor);
		$captch_code = "";
		$fontfile = $this->config->item('MYPATH') . 'Soopafresh.ttf';
		for ($i = 0; $i < 4; $i++) {
			$fontsize = 20;
			$fontcolor = imagecolorallocate($img, rand(0, 100), rand(0, 100), rand(0, 100));
			$date = "abcdefghjkmnpqrstuvwxyz23456789";
			$fontcontent = substr($date, rand(0, strlen($date)), 1);
			$captch_code .= $fontcontent;

			$x = ($i * 100 / 4) + rand(5, 10);
			$y = rand(25, 30);

			imagettftext($img, $fontsize, 0, $x, $y, $fontcolor, $fontfile, $fontcontent);
		}
		$this->session->set_userdata(array('user_authcode' => $captch_code));
		//点干扰
		for ($i = 0; $i < 200; $i++) {
			$pointcolor = imagecolorallocate($img, rand(50, 200), rand(50, 200), rand(50, 200));
			imagesetpixel($img, rand(1, 99), rand(1, 29), $pointcolor);

		}

		//线干扰
		for ($i = 0; $i < 3; $i++) {
			$linecolor = imagecolorallocate($img, rand(80, 220), rand(80, 220), rand(80, 220));
			imageline($img, rand(1, 99), rand(1, 29), rand(1, 99), rand(1, 29), $linecolor);
		}

		header('content-type:image/png');
		imagepng($img);
	}

	private function _get_type($data, $urldecode = 0, $type = 'json') {
		if ($type == 'array') {
			pring_r($data);
			exit;
		}
		if (!isset($data['code']) || !is_numeric($data['code'])) {
			return '';
		}
		if ($urldecode) {
			$json = urldecode(json_encode($data, JSON_UNESCAPED_UNICODE));
		} else {
			$json = json_encode($data, JSON_UNESCAPED_UNICODE);
		}
		echo $json;
		exit;
	}

	/*
		17级座次表
	 */
	public function mySite($action = 'see') {
		$data['site'] = json_decode(file_get_contents('site.json'));
		if ($action == 'set') {
			$row = $this->input->post('row');
			$col = $this->input->post('col');
			$name = $this->input->post('name');
			is_numeric($row) && is_numeric($col) && $row < 8 && $col < 4 ? '' : alert_msg('不要调皮！');
			$json_str = file_get_contents('site.json');
			$site_arr = json_decode($json_str);
			$site_arr[$row][$col] = $name;
			file_put_contents('site.json', json_encode($site_arr, JSON_UNESCAPED_UNICODE));
			$array = array(
				'code' => 200,
				'name' => $name,
				'row' => $row,
				'col' => $col,
			);
			$this->_get_type($array);
		} else {
			$this->load->view('my_site.html', $data);
		}
	}
	/************************ 前台业务逻辑 end ************************/
}
