<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Admin_model extends CI_Model {

	/*
		管理员验证
	*/
	public function get_admin_info($username, $password) {
		$status = $this->db->select('id, identity')->get_where('admin', array('username' => $username, 'password' => $password))->result_array();
		return $status;
	}

	/****************** 用户相关相关 begin *******************/

	/*
		获取用户列表
	 */
	public function get_user_list($where_arr, $offset, $per_page = 10, $other_info = '') {
		$get_info = 'user_id, email, identity, phone, register_time, status, realname' . $other_info;
		$status = $this->db->select($get_info)->order_by('register_time DESC')->limit($per_page, $offset)->get_where('user', $where_arr)->result_array();
		return $status;
	}

	/*
		获取单个用户信息
	 */
	public function get_user_info($where_arr, $other_info = '') {
		$get_info = 'user_id, email, identity, sex, address, phone, major, research_direction, postcode, organization, qq, edu_background, status' . $other_info;
		$status = $this->db->select('*')->get_where('user', $where_arr)->result_array();
		return $status;
	}

	/*
		搜索用户
	*/
	public function get_user_search($where_arr, $like_msg, $other_info = '') {
		$get_info = 'user_id, email, identity, sex, address, phone, major, research_direction, postcode, organization, qq, edu_background, status' . $other_info;
		$status = $this->db->select('*')->like('realname', $like_msg)->or_like('email', $like_msg)->or_like('phone', $like_msg)->get_where('user', $where_arr)->result_array();
		return $status;
	}

	/****************** 用户相关相关 END *******************/

	/****************** 稿件相关  BEGIN  *******************/

	//获取稿件列表
	public function get_article_list($where_arr, $offset, $other_info = '', $per_page = 10) {
		$get_info = 'article_id, title, keywords, create_time, check_status, check_deadline' . $other_info;
		$status = $this->db->select($get_info)->order_by('create_time DESC')->limit($per_page, $offset)->get_where('article', $where_arr)->result_array();
		return $status;
	}

	//获取稿件信息
	public function get_article_info($where_arr) {
		$status = $this->db->select('*')->get_where('article', $where_arr)->result_array();
		return $status;
	}

	//获取稿件评论信息
	public function get_suggest_info($where_arr, $other_info = '') {
		$get_info = 'sug_id, , suggest.user_id, content, rank, time, realname';
		$status = $this->db->select($get_info)->join('user', 'user.user_id = suggest.user_id')->order_by('rank ASC')->get_where('suggest', $where_arr)->result_array();
		return $status;
	}

	/****************** 稿件相关  End  *******************/

	/****************** 前台管理相关  BEGIN  *******************/

	//留言管理
	public function get_comment_list($where_arr, $offset, $per_page) {
		$status = $this->db->order_by('time DESC')->limit($per_page, $offset)->get_where('comment', $where_arr)->result_array();
		return $status;
	}
	//获取栏目相关
	public function get_col_info($where_arr) {
		$status = $this->db->get_where('col', $where_arr)->result_array();
		return $status;
	}

	//获取内容列表
	public function get_content_list($where_arr, $offset, $per_page = 10) {
		$status = $this->db->order_by('is_top DESC, time DESC')->limit($per_page, $offset)->get_where('content', $where_arr)->result_array();
		return $status;
	}

	//获取内容相关信息
	public function get_content_info($where_arr) {
		$status = $this->db->get_where('content', $where_arr)->result_array();
		return $status;
	}

	/****************** 前台管理相关  End  *******************/
}
