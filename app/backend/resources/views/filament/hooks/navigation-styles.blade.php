@if($isAdmin2)
<style>
    /* 管理者権限以外のメニューを非表示 */
    [data-group="サービス記録管理"],
    [data-group="マスタ管理"],
    [data-group="システム管理"] {
        display: none !important;
    }
</style>
@endif