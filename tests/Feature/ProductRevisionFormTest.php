<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRevisionFormTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $product = Product::create(['code' => 'P-'.uniqid(), 'name' => 'Valve body']);
        ProductRevision::create(['product_id' => $product->id, 'revision' => '0', 'status' => 'APPROVED']);

        return $product;
    }

    public function test_the_add_revision_form_is_hidden_above_the_list_until_the_button_is_pressed(): void
    {
        $page = $this->get(route('products.show', $this->product()))->assertOk()
            ->assertSee('data-rev-add', false);

        $html = $page->getContent();
        $this->assertMatchesRegularExpression('/id="add-revision"\s+hidden/', $html);
        $this->assertLessThan(strpos($html, '<th>Net weight</th>'), strpos($html, 'id="add-revision"'));
    }

    public function test_the_form_opens_again_when_adding_a_revision_fails(): void
    {
        $product = $this->product();

        $this->from(route('products.show', $product))->post(route('products.revisions.store', $product), ['revision' => '0', 'status' => 'DRAFT']);

        $this->assertDoesNotMatchRegularExpression('/id="add-revision"\s+hidden/', $this->get(route('products.show', $product))->assertOk()->getContent());
    }
}
