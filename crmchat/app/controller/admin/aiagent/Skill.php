<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | Skill 管理后台接口控制器
// +----------------------------------------------------------------------

namespace app\controller\admin\aiagent;

use app\controller\admin\AuthController;
use app\services\aiagent\SkillRepository;

/**
 * Skill 管理后台接口（直接读写 aiagent/skills 下的 SKILL.md 文件）
 * Class Skill
 * @package app\controller\admin\aiagent
 */
class Skill extends AuthController
{
    /**
     * @var SkillRepository Skill 仓库服务
     */
    protected $skills;

    /**
     * Skill constructor.
     * @param SkillRepository $skills
     */
    public function __construct(SkillRepository $skills)
    {
        parent::__construct();
        $this->skills = $skills;
    }

    /**
     * Skill 列表
     * @return mixed
     */
    public function index()
    {
        return $this->success('ok', $this->skills->all());
    }

    /**
     * Skill 详情
     * @param string $key Skill 标识
     * @return mixed
     */
    public function read(string $key)
    {
        return $this->success('ok', $this->skills->detail($key));
    }

    /**
     * 新建 Skill
     * @return mixed
     */
    public function save()
    {
        $key = $this->skills->create((array)$this->request->param());
        return $this->success('保存成功', ['key' => $key]);
    }

    /**
     * 更新 Skill
     * @param string $key Skill 标识
     * @return mixed
     */
    public function update(string $key)
    {
        $this->skills->update($key, (array)$this->request->param());
        return $this->success('保存成功');
    }

    /**
     * 删除 Skill
     * @param string $key Skill 标识
     * @return mixed
     */
    public function delete(string $key)
    {
        $this->skills->delete($key);
        return $this->success('删除成功');
    }

    /**
     * 修改启用状态
     * @param string $key Skill 标识
     * @return mixed
     */
    public function status(string $key)
    {
        $this->skills->setStatus($key, (int)$this->request->param('status', 0));
        return $this->success('修改成功');
    }

    /**
     * Skill 附属资料列表（随 SKILL.md 存储在技能目录下的 .md 文件，Agent 经 crmeb_skill_load 按需加载）
     * @param string $key Skill 标识
     * @return mixed
     */
    public function attachmentList(string $key)
    {
        $this->skills->detail($key);
        return $this->success('ok', $this->skills->attachments($key));
    }

    /**
     * Skill 附属资料详情（含全文，file 经查询参数传入，避免中文文件名进路由）
     * @param string $key Skill 标识
     * @return mixed
     */
    public function attachmentRead(string $key)
    {
        return $this->success('ok', $this->skills->attachmentDetail($key, (string)$this->request->param('file', '')));
    }

    /**
     * 保存（新建或覆盖）Skill 附属资料
     * @param string $key Skill 标识
     * @return mixed
     */
    public function attachmentSave(string $key)
    {
        $data = (array)$this->request->param();
        $this->skills->saveAttachment($key, (string)($data['file'] ?? ''), (string)($data['content'] ?? ''));
        return $this->success('保存成功');
    }

    /**
     * 删除 Skill 附属资料（file 经查询参数传入）
     * @param string $key Skill 标识
     * @return mixed
     */
    public function attachmentDelete(string $key)
    {
        $this->skills->deleteAttachment($key, (string)$this->request->param('file', ''));
        return $this->success('删除成功');
    }
}
