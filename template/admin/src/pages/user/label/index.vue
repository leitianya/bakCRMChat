<template>
  <div>
    <div class="i-layout-page-header">
      <div class="i-layout-page-header">
        <span class="ivu-page-header-title">{{$route.meta.title}}</span>
      </div>
    </div>
    <Card class="ivu-mt box-wrapper" :bordered="false" dis-hover>
      <div class="header-btn">
        <Button v-auth="['admin-user-label_add']" icon="md-add" @click="add">添加标签</Button>
        <Button v-auth="['admin-user-label_add']" type="primary" icon="md-add" class="header-btn-primary" @click="openCreateModal">添加标签组</Button>
      </div>
      <Table :columns="columns" :data="groupList" ref="table" class="label-table mt25" :loading="loading" no-data-text="暂无数据">
        <template slot-scope="{ row, index }" slot="drag">
          <Icon class="drag-handle" type="md-menu" size="16" />
        </template>
        <template slot-scope="{ row, index }" slot="type">
          <span class="type-badge">手动标签</span>
        </template>
        <template slot-scope="{ row, index }" slot="labels">
          <div class="tag-chips" v-if="row.label && row.label.length">
            <span class="tag-chip" v-for="label in row.label" :key="label.id" :title="'点击编辑标签组'" @click="openGroupModal(row)">{{ label.label }}</span>
          </div>
          <span class="tag-empty" v-else>暂无标签</span>
        </template>
        <template slot-scope="{ row, index }" slot="action">
          <a @click="openGroupModal(row)">编辑</a>
          <a class="del-link" @click="removeGroup(row)">删除</a>
        </template>
      </Table>

      <!-- 编辑/添加标签组弹窗 -->
      <Modal v-model="modalShow" :title="modalIsCreate ? '添加标签组' : '编辑标签组'" :width="720" :mask-closable="false" class="group-modal" @on-visible-change="modalVisibleChange">
        <div class="group-form">
          <div class="form-item">
            <div class="form-label">标签组名称</div>
            <Input v-model="modalForm.name" size="large" placeholder="请输入标签组名称" />
          </div>
          <div class="form-item">
            <div class="form-label">标签组类型</div>
            <RadioGroup v-model="modalForm.type">
              <Radio label="manual">手动标签</Radio>
              <Radio label="system" disabled>系统标签</Radio>
            </RadioGroup>
          </div>
          <div class="form-item">
            <div class="form-label">标签</div>
            <div class="tag-rows" ref="tagRows">
              <div class="tag-row" v-for="(tag, index) in modalForm.tags" :key="tag.key">
                <Input v-model="tag.label" size="large" placeholder="请输入标签名称" class="tag-row-input" />
                <Icon class="row-drag" type="md-menu" size="18" title="拖拽排序" />
                <Icon class="row-del" type="ios-trash-outline" size="18" title="删除标签" @click.native="delModalTag(index)" />
              </div>
            </div>
            <a class="add-tag-link" @click="addModalTag">
              <Icon type="md-add" /> 添加标签
            </a>
          </div>
        </div>
        <div slot="footer" class="modal-footer">
          <a class="del-group" v-if="!modalIsCreate" @click="delGroup">删除标签组</a>
          <div class="footer-btns">
            <Button @click="modalShow = false">取消</Button>
            <Button type="primary" :loading="saveLoading" @click="saveGroup">确定</Button>
          </div>
        </div>
      </Modal>
    </Card>
  </div>
</template>

<script>
import { userLabelAll, userLabelApi, userLabelAddApi, userLabelMoveCate, userLabelMove, userLabelCateSave, userLabelCateUpdate, userLabelCateDel, userLabelSave, userLabelUpdate, userLabelDel } from '@/api/user';
import { Icon } from 'iview';
import { Sortable } from "sortablejs";
export default {
  name: 'user_label',
  data() {
    return {
      loading: false,
      // 标签组列表（每组内嵌 label 标签数组）
      groupList: [],
      // 表格列
      columns: [
        {
          title: ' ',
          width: 50,
          slot: 'drag'
        },
        {
          title: '标签组名称',
          key: 'name',
          width: 180
        },
        {
          title: '标签类型',
          slot: 'type',
          width: 130
        },
        {
          title: '标签',
          slot: 'labels',
          minWidth: 400
        },
        {
          title: '操作',
          slot: 'action',
          width: 130,
          align: 'right'
        }
      ],
      // 弹窗状态
      modalShow: false,
      modalIsCreate: false,
      saveLoading: false,
      modalForm: {
        id: 0,
        name: '',
        sort: 0,
        type: 'manual',
        tags: []
      },
      // 弹窗内被删除的已有标签 id
      modalRemovedIds: [],
      tagKeySeq: 0,
      rowSortable: null
    }
  },
  created() {
    this.loadData();
  },
  mounted() {
    this.$nextTick(() => {
      this.initRowSortable();
    });
  },
  beforeDestroy() {
    this.rowSortable && this.rowSortable.destroy();
  },
  methods: {
    // 加载标签组及其下属标签，按 cate_id 归组
    loadData() {
      this.loading = true;
      Promise.all([userLabelAll(), this.getAllLabels()]).then(([cateRes, labels]) => {
        const cates = cateRes.data.data || [];
        this.groupList = cates.map(cate => ({
          ...cate,
          label: labels.filter(label => label.cate_id === cate.id)
        }));
        this.loading = false;
      }).catch(res => {
        this.loading = false;
        this.$Message.error(res.msg);
      })
    },
    // 标签按每页 50 条分页取全
    getAllLabels() {
      const pageLimit = 50;
      return userLabelApi({ page: 1, limit: pageLimit }).then(async res => {
        let list = res.data.list || [];
        const count = res.data.count || 0;
        const totalPages = Math.ceil(count / pageLimit);
        for (let page = 2; page <= totalPages; page++) {
          const pageRes = await userLabelApi({ page, limit: pageLimit });
          list = list.concat(pageRes.data.list || []);
        }
        return list;
      });
    },
    // 标签组拖拽排序
    initRowSortable() {
      new Sortable(document.querySelector('.ivu-table-tbody'), {
        handle: '.drag-handle',
        onEnd: ({ newIndex, oldIndex }) => {
          if (newIndex === oldIndex) return;
          let row = this.groupList.splice(oldIndex, 1)[0];
          this.groupList.splice(newIndex, 0, row);
          const ids = this.groupList.map(res => res.id);
          const list = this.groupList;
          this.groupList = [];
          this.$nextTick(() => {
            this.groupList = list;
          });
          userLabelMoveCate({
            ids: ids
          }).catch(err => {
            this.$Message.error(err.msg);
          });
        }
      });
    },
    // 弹窗内标签行拖拽排序
    initModalDrag() {
      this.rowSortable && this.rowSortable.destroy();
      this.rowSortable = null;
      const el = this.$refs.tagRows;
      if (!el) return;
      this.rowSortable = new Sortable(el, {
        handle: '.row-drag',
        onEnd: ({ newIndex, oldIndex }) => {
          if (newIndex === oldIndex) return;
          const row = this.modalForm.tags.splice(oldIndex, 1)[0];
          this.modalForm.tags.splice(newIndex, 0, row);
        }
      });
    },
    // 弹窗显示变化时初始化拖拽
    modalVisibleChange(visible) {
      if (visible) {
        this.$nextTick(() => {
          this.initModalDrag();
        });
      }
    },
    // 打开编辑标签组弹窗
    openGroupModal(row) {
      this.modalIsCreate = false;
      this.modalForm = {
        id: row.id,
        name: row.name,
        sort: row.sort,
        type: 'manual',
        tags: (row.label || []).map(label => ({
          id: label.id,
          label: label.label,
          sort: label.sort,
          key: ++this.tagKeySeq
        }))
      };
      this.modalRemovedIds = [];
      this.modalShow = true;
    },
    // 打开添加标签组弹窗
    openCreateModal() {
      this.modalIsCreate = true;
      this.modalForm = {
        id: 0,
        name: '',
        sort: 0,
        type: 'manual',
        tags: [this.emptyTagRow()]
      };
      this.modalRemovedIds = [];
      this.modalShow = true;
    },
    // 空白标签行
    emptyTagRow() {
      return {
        id: 0,
        label: '',
        sort: 0,
        key: ++this.tagKeySeq
      };
    },
    // 弹窗内追加标签行
    addModalTag() {
      this.modalForm.tags.push(this.emptyTagRow());
    },
    // 弹窗内删除标签行（已有标签记入待删除列表）
    delModalTag(index) {
      const row = this.modalForm.tags[index];
      if (row && row.id) {
        this.modalRemovedIds.push(row.id);
      }
      this.modalForm.tags.splice(index, 1);
    },
    // 保存标签组（整组：名称 + 标签增删改 + 顺序）
    async saveGroup() {
      const name = this.modalForm.name.trim();
      if (!name) {
        this.$Message.error('请输入标签组名称');
        return;
      }
      // 输入被清空的已有标签视为删除，空的新建行直接忽略
      const rows = this.modalForm.tags.filter(tag => tag.label.trim() !== '');
      const removedIds = [...this.modalRemovedIds];
      this.modalForm.tags.forEach(tag => {
        if (tag.id && tag.label.trim() === '' && !removedIds.includes(tag.id)) {
          removedIds.push(tag.id);
        }
      });
      this.saveLoading = true;
      try {
        let cateId = this.modalForm.id;
        if (cateId) {
          await userLabelCateUpdate(cateId, { name, sort: this.modalForm.sort });
        } else {
          const res = await userLabelCateSave({ name });
          cateId = res.data.id;
        }
        const finalIds = [];
        for (const row of rows) {
          const label = row.label.trim();
          if (row.id) {
            await userLabelUpdate(row.id, { cate_id: cateId, label, sort: row.sort });
            finalIds.push(row.id);
          } else {
            const res = await userLabelSave({ cate_id: cateId, label });
            finalIds.push(res.data.id);
          }
        }
        for (const id of removedIds) {
          await userLabelDel(id);
        }
        // 重建组内标签顺序（首个 id 排序最高）
        if (finalIds.length) {
          await userLabelMove({ ids: finalIds, page: 1 });
        }
        this.$Message.success('保存成功');
        this.modalShow = false;
        this.loadData();
      } catch (e) {
        this.$Message.error((e && e.msg) || '保存失败');
      } finally {
        this.saveLoading = false;
      }
    },
    // 删除标签组（连同组内标签，标签已关联用户时后端会拒绝）
    removeGroup(row) {
      const tags = (row.label || []).filter(label => label.id);
      this.$Modal.confirm({
        title: '删除标签组',
        content: tags.length ? `删除后不可恢复，组内 ${tags.length} 个标签将一并删除，确定删除吗？` : '确定删除该标签组吗？',
        onOk: async () => {
          try {
            for (const label of tags) {
              await userLabelDel(label.id);
            }
            await userLabelCateDel(row.id);
            this.$Message.success('删除成功');
            this.loadData();
          } catch (e) {
            this.$Message.error((e && e.msg) || '删除失败');
            this.loadData();
          }
        }
      });
    },
    // 弹窗底部删除标签组
    delGroup() {
      if (!this.modalForm.id) return;
      this.removeGroup({ id: this.modalForm.id, label: this.modalForm.tags });
      this.modalShow = false;
    },
    // 添加标签（页面右上角快捷入口）
    add() {
      this.$modalForm(userLabelAddApi()).then(() => this.loadData());
    }
  }
}
</script>

<style lang="stylus" scoped>
.header-btn {
  display: flex;
  justify-content: flex-end;

  .header-btn-primary {
    margin-left: 10px;
  }
}

.label-table {
  .drag-handle {
    cursor: move;
    color: #c5c8ce;
  }

  .type-badge {
    display: inline-block;
    padding: 3px 12px;
    border: 1px solid #2d8cf0;
    border-radius: 4px;
    background: #fff;
    color: #2d8cf0;
    font-size: 12px;
  }

  .tag-chips {
    display: flex;
    flex-wrap: wrap;
  }

  .tag-chip {
    display: inline-block;
    padding: 5px 16px;
    margin: 2px 16px 8px 0;
    border: 1px solid #dcdee2;
    border-radius: 4px;
    background: #fff;
    color: #515a6e;
    font-size: 13px;
    cursor: pointer;
    user-select: none;

    &:hover {
      border-color: #2d8cf0;
      color: #2d8cf0;
    }
  }

  .tag-empty {
    color: #999;
  }

  .del-link {
    margin-left: 16px;
  }
}

.group-modal {
  .group-form {
    padding: 0 4px;

    .form-item {
      margin-bottom: 22px;
    }

    .form-label {
      font-size: 14px;
      color: #17233d;
      margin-bottom: 12px;
    }

    .tag-row {
      display: flex;
      align-items: center;
      margin-bottom: 12px;
    }

    .tag-row-input {
      flex: 1;
    }

    .row-drag {
      cursor: move;
      color: #c5c8ce;
      margin-left: 16px;
    }

    .row-del {
      cursor: pointer;
      color: #808695;
      margin-left: 16px;

      &:hover {
        color: #ed4014;
      }
    }

    .add-tag-link {
      display: inline-block;
      margin-top: 2px;
    }
  }

  .modal-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;

    .del-group {
      color: #ed4014;
      font-size: 14px;
    }
  }
}
</style>
