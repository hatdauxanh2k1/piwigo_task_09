<div class="container-fluid">
  <div class="row">
    <div class="col-md-12">
      <div id="news-slider" class="owl-carousel" items="{$SLIDER.items}" items-desktop="{$SLIDER.itemsDesktop}" items-desktop-small="{$SLIDER.itemsDesktopSmall}" items-mobile="{$SLIDER.itemsMobile}">
      {foreach from=$SLIDER.elements item=picture}
        {assign var=derivative value=$pwg->derivative($SLIDER.derivative_params, $picture.src_image)}
        <div class="post-slide">
          <div class="post-img">
            <img {if $derivative->is_cached()}src="{$derivative->get_url()}"{else}src="" data-src="{$derivative->get_url()}"{/if} alt="{$picture.TN_ALT}" {$derivative->get_size_htm()} {if $SLIDER.title}title=""{/if} {if $SLIDER.control_thumbs}data-thumb="{$derivative_thumb}"{/if}>
            <a href="{$picture.URL}" class="over-layer"><i class="fa fa-link"></i></a>
          </div>
          <div class="post-content">
            <h3 class="post-title">
              <a href="{$picture.URL}">{$picture.NAME}</a>
            </h3>
            <p class="post-description">{$picture.comment}</p>
            <span class="post-date"><i class="fa fa-clock-o"></i>{$picture.date_available}</span>
            <a href="{$picture.URL}" class="read-more">read more</a>
          </div>
        </div>
      {/foreach}
      </div>
    </div>
  </div>
</div>
