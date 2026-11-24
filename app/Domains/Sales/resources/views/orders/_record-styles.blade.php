{{-- The sales order list and record pages (designs 1e, 6a): the shared record styles plus the facts strip and to-do cards. --}}
@include('partials.record-ui')
<style>
    .el-facts { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 18px; padding: 18px 28px; font-size: 13px; border-bottom: 1px solid var(--border); }
    .el-todo { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 16px; align-items: center; padding: 12px 14px; border: 1px solid #f3dfb8; border-radius: 8px; background: var(--surface, #fff); } .el-todo.bad { border-color: #f2cfcb; }
    .el-todo-t { font-weight: 600; font-size: 13px; color: #94610a; } .el-todo.bad .el-todo-t { color: #b3352c; } .el-todo-m { font-size: 12.5px; color: #55585d; margin-top: 2px; }
    @media (max-width: 1100px) { .el-facts { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
</style>
