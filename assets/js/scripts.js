/**
 * jQuery Geocoding and Places Autocomplete Plugin - V 1.6.1
 *
 * @author Martin Kleppe <kleppe@ubilabs.net>, 2014
 * @author Ubilabs http://ubilabs.net, 2014
 * @license MIT License <http://www.opensource.org/licenses/mit-license.php>
 */
(function($,window,document,undefined){var defaults={bounds:true,country:null,map:false,details:false,detailsAttribute:"name",autoselect:true,location:false,mapOptions:{zoom:14,scrollwheel:false,mapTypeId:"roadmap"},markerOptions:{draggable:false},maxZoom:16,types:["geocode"],blur:false};var componentTypes=("street_address route intersection political "+"country administrative_area_level_1 administrative_area_level_2 "+"administrative_area_level_3 colloquial_area locality sublocality "+"neighborhood premise subpremise postal_code natural_feature airport "+"park point_of_interest post_box street_number floor room "+"lat lng viewport location "+"formatted_address location_type bounds").split(" ");var placesDetails=("id place_id url website vicinity reference name rating "+"international_phone_number icon formatted_phone_number").split(" ");function GeoComplete(input,options){this.options=$.extend(true,{},defaults,options);this.input=input;this.$input=$(input);this._defaults=defaults;this._name="geocomplete";this.init()}$.extend(GeoComplete.prototype,{init:function(){this.initMap();this.initMarker();this.initGeocoder();this.initDetails();this.initLocation()},initMap:function(){if(!this.options.map){return}if(typeof this.options.map.setCenter=="function"){this.map=this.options.map;return}this.map=new google.maps.Map($(this.options.map)[0],this.options.mapOptions);google.maps.event.addListener(this.map,"click",$.proxy(this.mapClicked,this));google.maps.event.addListener(this.map,"zoom_changed",$.proxy(this.mapZoomed,this))},initMarker:function(){if(!this.map){return}var options=$.extend(this.options.markerOptions,{map:this.map});if(options.disabled){return}this.marker=new google.maps.Marker(options);google.maps.event.addListener(this.marker,"dragend",$.proxy(this.markerDragged,this))},initGeocoder:function(){var options={types:this.options.types,bounds:this.options.bounds===true?null:this.options.bounds,componentRestrictions:this.options.componentRestrictions};if(this.options.country){options.componentRestrictions={country:this.options.country}}this.autocomplete=new google.maps.places.Autocomplete(this.input,options);this.geocoder=new google.maps.Geocoder;if(this.map&&this.options.bounds===true){this.autocomplete.bindTo("bounds",this.map)}google.maps.event.addListener(this.autocomplete,"place_changed",$.proxy(this.placeChanged,this));this.$input.keypress(function(event){if(event.keyCode===13){return false}});this.$input.bind("geocode",$.proxy(function(){this.find()},this));if(this.options.blur===true){this.$input.blur($.proxy(function(){this.find()},this))}},initDetails:function(){if(!this.options.details){return}var $details=$(this.options.details),attribute=this.options.detailsAttribute,details={};function setDetail(value){details[value]=$details.find("["+attribute+"="+value+"]")}$.each(componentTypes,function(index,key){setDetail(key);setDetail(key+"_short")});$.each(placesDetails,function(index,key){setDetail(key)});this.$details=$details;this.details=details},initLocation:function(){var location=this.options.location,latLng;if(!location){return}if(typeof location=="string"){this.find(location);return}if(location instanceof Array){latLng=new google.maps.LatLng(location[0],location[1])}if(location instanceof google.maps.LatLng){latLng=location}if(latLng){if(this.map){this.map.setCenter(latLng)}if(this.marker){this.marker.setPosition(latLng)}}},find:function(address){this.geocode({address:address||this.$input.val()})},geocode:function(request){if(this.options.bounds&&!request.bounds){if(this.options.bounds===true){request.bounds=this.map&&this.map.getBounds()}else{request.bounds=this.options.bounds}}if(this.options.country){request.region=this.options.country}this.geocoder.geocode(request,$.proxy(this.handleGeocode,this))},selectFirstResult:function(){var selected="";if($(".pac-item-selected")["0"]){selected="-selected"}var $span1=$(".pac-container .pac-item"+selected+":first span:nth-child(2)").text();var $span2=$(".pac-container .pac-item"+selected+":first span:nth-child(3)").text();var firstResult=$span1;if($span2){firstResult+=" - "+$span2}this.$input.val(firstResult);return firstResult},handleGeocode:function(results,status){if(status===google.maps.GeocoderStatus.OK){var result=results[0];this.$input.val(result.formatted_address);this.update(result);if(results.length>1){this.trigger("geocode:multiple",results)}}else{this.trigger("geocode:error",status)}},trigger:function(event,argument){this.$input.trigger(event,[argument])},center:function(geometry){if(geometry.viewport){this.map.fitBounds(geometry.viewport);if(this.map.getZoom()>this.options.maxZoom){this.map.setZoom(this.options.maxZoom)}}else{this.map.setZoom(this.options.maxZoom);this.map.setCenter(geometry.location)}if(this.marker){this.marker.setPosition(geometry.location);this.marker.setAnimation(this.options.markerOptions.animation)}},update:function(result){if(this.map){this.center(result.geometry)}if(this.$details){this.fillDetails(result)}this.trigger("geocode:result",result)},fillDetails:function(result){var data={},geometry=result.geometry,viewport=geometry.viewport,bounds=geometry.bounds;$.each(result.address_components,function(index,object){var name=object.types[0];$.each(object.types,function(index,name){data[name]=object.long_name;data[name+"_short"]=object.short_name})});$.each(placesDetails,function(index,key){data[key]=result[key]});$.extend(data,{formatted_address:result.formatted_address,location_type:geometry.location_type||"PLACES",viewport:viewport,bounds:bounds,location:geometry.location,lat:geometry.location.lat(),lng:geometry.location.lng()});$.each(this.details,$.proxy(function(key,$detail){var value=data[key];this.setDetail($detail,value)},this));this.data=data},setDetail:function($element,value){if(value===undefined){value=""}else if(typeof value.toUrlValue=="function"){value=value.toUrlValue()}if($element.is(":input")){$element.val(value)}else{$element.text(value)}},markerDragged:function(event){this.trigger("geocode:dragged",event.latLng)},mapClicked:function(event){this.trigger("geocode:click",event.latLng)},mapZoomed:function(event){this.trigger("geocode:zoom",this.map.getZoom())},resetMarker:function(){this.marker.setPosition(this.data.location);this.setDetail(this.details.lat,this.data.location.lat());this.setDetail(this.details.lng,this.data.location.lng())},placeChanged:function(){var place=this.autocomplete.getPlace();if(!place||!place.geometry){if(this.options.autoselect){var autoSelection=this.selectFirstResult();this.find(autoSelection)}}else{this.update(place)}}});$.fn.geocomplete=function(options){var attribute="plugin_geocomplete";if(typeof options=="string"){var instance=$(this).data(attribute)||$(this).geocomplete().data(attribute),prop=instance[options];if(typeof prop=="function"){prop.apply(instance,Array.prototype.slice.call(arguments,1));return $(this)}else{if(arguments.length==2){prop=arguments[1]}return prop}}else{return this.each(function(){var instance=$.data(this,attribute);if(!instance){instance=new GeoComplete(this,options);$.data(this,attribute,instance)}})}}})(jQuery,window,document);

// Application specific scripts
$('document').ready(function() {
	// Simple AJAX listeners
	$(document).bind("ajaxSend", function(){
		$('.btn-primary').attr('disabled', 'disabled');
	}).bind("ajaxComplete", function(){
		$('.btn-primary').removeAttr('disabled');
	});

	// Google Places address autocomplete
	$("#address").geocomplete({
  	map: "#map_canvas",
	  mapOptions: {
	    mapTypeId: 'hybrid',
	    disableDefaultUI: true
	  }
	}).bind("geocode:result", function(event, result){
    $('#step-one .btn-primary').removeClass('disabled').removeAttr('disabled');
  });

  // Show offer modal
  $('#get-offer').click(function () {
    $('#valuator-offer').modal('show');

    return false;
  });

	// Simple PubSub
  var o = $({});
  $.subscribe = function() { o.on.apply(o, arguments) };
  $.publish = function() { o.trigger.apply(o, arguments) };

  // Submit form via AJAX
  var submitAjax = function(e) {
	  var form = $(this);
	  var method = form.find('input[name="_method"]').val() || 'POST';

	  $.ajax({
      type: method,
      url: Valuator.ajaxurl,
      data: form.serialize(),
      dataType: 'json',
      async: true,
      success: function(response) {
      	$.publish('ajax.request.success', [form, response]);
      }
	  });

	  e.preventDefault();
  };

	// Handle AJAX request callbacks
  $.subscribe('ajax.request.success', function(e, form, response) {
    triggerRequestCallback.apply(form, [e, $(form).data('remote-on-success'), response]);
  });

  // Trigger the registered callback for a click or form submission.
  var triggerRequestCallback = function(e, method, response) {
    var that = $(this);

    if ( ! (model = that.closest('*[data-model]').data('model'))) {
      return;
    }

    if (typeof window[model] == 'object' && typeof window[model][method] == 'function') {
        window[model][method](that, response);
    } else {
        console.error('Could not call method ' + method + ' on object ' + model);
    }

    e.preventDefault();
  }

  // Dom bindings.
  $('form[data-remote]').on('submit', submitAjax);
  $('*[data-click]').on('click', function(e) {
    triggerRequestCallback.apply(this, [e, $(this).data('click')]);
  });

	// Step one form submission
	window.stepOne = {};
  stepOne.process = function(form, response) {
    $('#property_id').val( response.property_id );
    $('#property_id_complete').val( response.property_id );
    $('#step-one-well').addClass('animated fadeOutLeftBig');
    setTimeout(function () {
	    $('#step-one-well').hide();
	    $('#step-two-well').show().addClass('animated fadeInRightBig');
	    $('.valuation-page').css('padding-top', '0px');
	  }, 200);

    setTimeout(function() {
			google.maps.event.trigger($("#map_canvas")[0], 'resize');
			$("#address").geocomplete("find", $('#address').val());

			setTimeout(function() {
				var map = $("#address").geocomplete("map");
				map.setZoom(19);
			}, 500);
		}, 500);
  };

	// Step two form submission
	window.stepTwo = {};
  stepTwo.process = function(form, response) {
	  $('#step-two-well').removeClass('fadeInRightBig').addClass('fadeOutLeftBig');
	  setTimeout(function () {
		  $('#step-two-well').hide();
    	$('#step-three-well').show().addClass('animated fadeInRightBig');
			$('.valuation-page').css('padding-top', '0px');
			$('.single-valuator #page').css('min-height', '100%');
			$('.single-valuator #page').css('height', 'auto');
		}, 200);

    // Verify that we received a result
    if(typeof response.error != 'undefined')
    {
	    $('.valuation-value, .step-three-subtitle').remove();
	    $('.valuation-result').append('<h4 style="text-align: center;" class="landing-title">Your Home Value Report Will Be Sent Within 48 Hours!</h4>');
    }
    else
    {
	    // Fill in the valuation data
	    $('.low').text(response.low);
	    $('.estimated-value').text(response.amount);
	    $('.high').text(response.high);
	    $('.valuation-address').text(response.address);
	    $('.page-media').html(response.media);
	    if(typeof response.text != 'undefined'){
	      $('.page-text').html(response.text);
	    } else {
		    $('.page-text').remove();
	    }
	    $('#zip_code_copy').val( response.zip_code );
	  }

	  // Facebook events
	  var retargeting = $('#retargeting').val(),
        conversion = $('#conversion').val();
    if (retargeting != '') {
      (function () {
        var _fbq = window._fbq || (window._fbq = []);
        if (!_fbq.loaded) {
          var fbds = document.createElement('script');
          fbds.async = true;
          fbds.src = '//connect.facebook.net/en_US/fbds.js';
          var s = document.getElementsByTagName('script')[0];
          s.parentNode.insertBefore(fbds, s);
          _fbq.loaded = true;
        }
        _fbq.push(['addPixelId', retargeting]);
      })();
      window._fbq = window._fbq || [];
      window._fbq.push(['track', 'PixelInitialized', {}]);
    }
    if (conversion != '') {
      (function () {
        var _fbq = window._fbq || (window._fbq = []);
        if (!_fbq.loaded) {
          var fbds = document.createElement('script');
          fbds.async = true;
          fbds.src = '//connect.facebook.net/en_US/fbds.js';
          var s = document.getElementsByTagName('script')[0];
          s.parentNode.insertBefore(fbds, s);
          _fbq.loaded = true;
        }
      })();
      window._fbq = window._fbq || [];
      window._fbq.push(['track', conversion, {'value': '0.00', 'currency': 'USD'}]);
    }

    // Populate the step three form
    $('#first_name_copy').val( $('#first_name').val() );
    $('#last_name_copy').val( $('#last_name').val() );
    $('#email_copy').val( $('#email').val() );
    $('#address_copy').val( response.street );
    $('#address2_copy').val( $('#address_2').val() );
    $('#city_copy').val( response.city );
    $('#state_copy').val( response.state );

    // Populate the offer form
    $('#first_name_3').val( $('#first_name').val() );
    $('#last_name_3').val( $('#last_name').val() );
    $('#email_3').val( $('#email').val() );
	};

	// Step three form submission
	window.stepThree = {};
  stepThree.process = function(form, response) {
	  $('#step-three-well').removeClass('fadeInRightBig').addClass('fadeOutLeftBig');
    $('#valuator-offer').modal('hide');
	  setTimeout(function () {
		  $('.page-media').remove();
	  	$('#step-three-well').hide();
			$('#step-four-well').show().addClass('animated fadeInRightBig');
			$('.valuation-page').css('padding-top', '10%');
			$('.single-valuator #page').css('height', '100%');
			$('.single-valuator #page').css('min-height', 'auto');
		}, 200);
		setTimeout(function () {
			$('.thank-you').addClass('animated pulse');
		}, 1500);
	};
});
