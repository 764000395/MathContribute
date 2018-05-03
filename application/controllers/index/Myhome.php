<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * 个人中心
 */
class Myhome extends MY_Controller {
	public function __construct() {
		parent::__construct();
		$this->load->model('index_model');
	}

	public function index() {
		$identity = $this->session->userdata('identity');
		$this->load->view('myhome/index.html');
	}

	/*
		用户个人信息 查看和修改
	 */
	public function user_info($action = 'see') {
		if ($action == 'edit') {
			$user_info = array(
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
			);

			$status = $this->db->update('user', $user_info, array('user_id' => $this->session->userdata('user_id')));
			if ($status) {
				alert_msg('修改成功！');
			} else {
				alert_msg('修改失败，请重试！');
			}
		} else {
			$data = $this->index_model->show_user_info($this->session->userdata('user_id'))[0];
			$this->load->view('myhome/user_info.html', $data);
		}
	}

	/*
		用户登陆密码修改
	 */
	public function edit_password($action = 'see') {
		if ($action == 'edit') {
			$old_password = $this->input->post('old_password');

			//判断旧密码是否正确
			$status = $this->db->select('user_id')->get_where('user', array('user_id' => $this->session->userdata('user_id'), 'password' => md5($old_password)))->result_array();
			if (empty($status)) {
				alert_msg('旧密码不正确');
			}
			$new_password = $this->input->post('new_password');
			$re_password = $this->input->post('re_password');

			//判断两次输入密码是否一致
			if ($new_password != $re_password) {
				alert_msg('两次输入密码不一致');
			}

			//执行修改操作
			$status = $this->db->update('user', array('password' => md5($new_password)), array('user_id' => $this->session->userdata('user_id')));
			if ($status) {
				alert_msg('密码修改成功，请重新登录', 'go', site_url('home/login'));
			}
		} else {
			$this->load->view('myhome/edit_password.html');
		}
	}

	/*
		下载稿件，只有登陆权限限制,任何一个都可以下载
	 */
	public function download($article_id) {
		//article_id必须为数字，防止sql注入
		if (!is_numeric($article_id)) {
			alert_msg('无法进行下载！', 'close');
		}

		$where_arr = array('article_id' => $article_id);
		$article = $this->index_model->get_info_article($where_arr);
		if (empty($article)) {
			alert_msg('该稿件不存在，无法完下载！');
		}

		//如果是作者 判断是不是下载自己的稿件
		if ($this->session->userdata('identity') == 'author') {
			if ($this->session->userdata('user_id') != $article[0]['user_id']) {
				alert_msg('你无权下载该稿件！');
			}
		}

		//如果是专家，查看是否为自己审核的稿件
		if ($this->session->userdata('identity') == 'specialist') {
			$where_arr = array(
				'user_id' => $this->session->userdata('user_id'),
				'article_id' => $article_id,
			);
			if (empty($this->index_model->get_suggest_info($where_arr))) {
				alert_msg('您无权下载该稿件！');
			}
		}
		$this->load->helper('download');
		$data = file_get_contents($this->config->item('MYPATH') . $article[0]['attachment_url']);

		//匹配文件后缀名，重命名下载
		preg_match('/.\w+$/', $article[0]['attachment_url'], $matches);
		force_download($article[0]['title'] . $matches[0], $data);
	}

	/*
		用户安全退出
	 */
	public function logout() {
		$this->session->sess_destroy();
		alert_msg('退出成功', 'go', base_url());
	}
}
