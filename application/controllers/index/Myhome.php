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
		用户安全退出
	 */
	public function logout() {
		$this->session->sess_destroy();
		alert_msg('退出成功', 'go', base_url());
	}
}
